<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class VibeProfileManager
{
    private const EMBEDDING_DIMENSION = 1536;
    private const EMBEDDING_MODEL = 'fallback-php-1536';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function provisionForUser(User $user): void
    {
        $uid = $this->resolveUid($user);
        if ($uid === null) {
            return;
        }

        $originalText = $this->buildNarrative($user);
        $processedText = $this->normalizeText($originalText);
        $embeddingLiteral = $this->generateEmbeddingLiteral($processedText);

        try {
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO vibes (uid, original_vibe, vibe, embedding, embedding_model)
                    VALUES (:uid, :original, :processed, :embedding, :model)
                    ON CONFLICT (uid) DO UPDATE
                    SET original_vibe = EXCLUDED.original_vibe,
                        vibe = EXCLUDED.vibe,
                        embedding = EXCLUDED.embedding,
                        embedding_model = EXCLUDED.embedding_model,
                        updated_at = NOW()
                SQL,
                [
                    'uid' => $uid,
                    'original' => $originalText,
                    'processed' => $processedText,
                    'embedding' => $embeddingLiteral,
                    'model' => self::EMBEDDING_MODEL,
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to provision placeholder vibe profile.', [
                'uid' => $uid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveUid(User $user): ?string
    {
        $candidate = $user->getIntraLogin() ?: $user->getEmail();

        if ($candidate === null) {
            return null;
        }

        return substr($candidate, 0, 255);
    }

    private function buildNarrative(User $user): string
    {
        $segments = [];

        $display = $user->getDisplayName() ?: $user->getUsualFullName() ?: $user->getIntraLogin();
        if ($display) {
            $segments[] = sprintf('%s just joined 42Connect', $display);
        } else {
            $segments[] = 'A new 42 student just joined 42Connect';
        }

        if ($user->getKind()) {
            $segments[] = sprintf('profile type %s', $user->getKind());
        }

        if ($user->getLocation()) {
            $segments[] = sprintf('based in %s', $user->getLocation());
        }

        $campus = $user->getCampus();
        if (is_array($campus) && !empty($campus)) {
            $campusNames = array_filter(array_map(
                static fn ($item) => is_array($item) && isset($item['name']) ? $item['name'] : null,
                $campus,
            ));
            if ($campusNames) {
                $segments[] = sprintf('campus %s', implode(', ', array_slice($campusNames, 0, 3)));
            }
        }

        $projects = $user->getProjects();
        if (is_array($projects) && !empty($projects)) {
            $highlights = [];
            foreach ($projects as $project) {
                if (!is_array($project)) {
                    continue;
                }

                $name = $project['name'] ?? null;
                $status = $project['status'] ?? null;
                $mark = $project['final_mark'] ?? null;

                if ($name === null || $status === null) {
                    continue;
                }

                $snippet = sprintf('%s (%s)', $name, $status);
                if (is_numeric($mark)) {
                    $snippet .= sprintf(' mark %s', $mark);
                }
                $highlights[] = $snippet;

                if (count($highlights) >= 5) {
                    break;
                }
            }

            if ($highlights) {
                $segments[] = sprintf('projects %s', implode('; ', $highlights));
            }
        }

        $narrative = trim(implode('. ', $segments));

        if ($narrative === '') {
            $narrative = 'New 42 student on 42Connect';
        }

        return $this->truncate($narrative, 1000);
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        $normalized = preg_replace('/[^a-z0-9\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'new 42 student on 42connect';
    }

    private function generateEmbeddingLiteral(string $text): string
    {
        $vector = $this->generateDeterministicVector($text);
        $formatted = array_map(
            static fn (float $value): string => sprintf('%.6f', $value),
            $vector,
        );

        return '[' . implode(', ', $formatted) . ']';
    }

    /**
     * Create a deterministic pseudo-embedding so that every new user
     * has a row in the vibes table even before real embeddings exist.
     */
    private function generateDeterministicVector(string $text): array
    {
        $seedBytes = hash('sha512', $text !== '' ? $text : '42connect-vibes', true);
        $vector = [];
        $counter = 0;

        while (count($vector) < self::EMBEDDING_DIMENSION) {
            $digest = hash('sha512', $seedBytes . pack('N', $counter), true);
            $digestLength = strlen($digest);

            for ($offset = 0; $offset < $digestLength && count($vector) < self::EMBEDDING_DIMENSION; $offset += 4) {
                $chunk = substr($digest, $offset, 4);
                if ($chunk === '' || strlen($chunk) < 4) {
                    $chunk = str_pad($chunk, 4, "\0");
                }

                $int = unpack('N', $chunk)[1] ?? 0;
                $scaled = (($int % 2000000) / 1000000.0) - 1.0;
                $vector[] = $scaled;
            }

            $counter++;
        }

        $norm = sqrt(array_reduce(
            $vector,
            static fn (float $carry, float $value): float => $carry + ($value * $value),
            0.0,
        ));

        if ($norm <= 0) {
            // Fallback to unit vector along the first dimension
            $vector = array_fill(0, self::EMBEDDING_DIMENSION, 0.0);
            $vector[0] = 1.0;
            return $vector;
        }

        return array_map(
            static fn (float $value): float => $value / $norm,
            $vector,
        );
    }

    private function truncate(string $value, int $limit): string
    {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit - 1, 'UTF-8');
        return rtrim($truncated) . '…';
    }
}
