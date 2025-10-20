<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class DemoController extends AbstractController
{
    #[Route(path: '/demo', name: 'demo_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('demo/index.html.twig');
    }

    #[Route(path: '/run-python', name: 'demo_run_python', methods: ['POST'])]
    public function runPython(Request $request): JsonResponse
    {
        $text = (string) $request->request->get('text', 'Hello from Symfony');
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $scriptPath = $projectDir . '/scripts/run_demo.py';

        if (!is_readable($scriptPath)) {
            return $this->json(
                [
                    'success' => false,
                    'error' => sprintf('Script not found at %s', $scriptPath),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $process = new Process(['python3', $scriptPath, $text]);
        $process->setTimeout(10);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            return $this->json(
                [
                    'success' => false,
                    'error' => 'Python process failed',
                    'details' => $exception->getMessage(),
                    'stderr' => $process->getErrorOutput(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            return $this->json(
                [
                    'success' => false,
                    'error' => 'Unable to parse Python output as JSON',
                    'raw_output' => $output,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(
            [
                'success' => true,
                'data' => $decoded,
            ],
        );
    }
}
