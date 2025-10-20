<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Install pgvector extension and create vibes table for python_vibe_match integration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS vibes (
                uid VARCHAR(255) NOT NULL,
                original_vibe TEXT NOT NULL,
                vibe TEXT NOT NULL,
                embedding vector(1536) NOT NULL,
                embedding_model VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY(uid)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS vibes_embedding_idx ON vibes USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');
        $this->addSql(<<<'SQL'
            CREATE TRIGGER vibes_set_updated_at
            BEFORE UPDATE ON vibes
            FOR EACH ROW
            EXECUTE PROCEDURE set_updated_at();
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');
        $this->addSql('DROP FUNCTION IF EXISTS set_updated_at');
        $this->addSql('DROP TABLE IF EXISTS vibes');
        $this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
