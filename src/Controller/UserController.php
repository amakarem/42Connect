<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    #[Route('/user/toggle-ready', name: 'user_toggle_ready', methods: ['POST'])]
    public function toggleReady(EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Toggle readyToHelp
        $user->setReadyToHelp(!$user->isReadyToHelp());
        $em->flush();

        // If using AJAX, return JSON
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'readyToHelp' => $user->isReadyToHelp()
            ]);
        }

        // Otherwise redirect back to home
        return $this->redirectToRoute('app_home');
    }

    #[Route('/user/update_original_vibe', name: 'user_update_original_vibe', methods: ['POST'])]
    public function updateOriginalVibe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $originalVibeText = $request->request->get('originalVibe', '');

        $vibe = $user->getVibe(); // You need a getVibe() method in User
        if (!$vibe) {
            $vibe = new \App\Entity\Vibe();
            $vibe->setUser($user);
        }

        $vibe->setOriginalVibe($originalVibeText);
        $vibe->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($vibe);
        $em->flush();

        return $this->json(['status' => 'success', 'originalVibe' => $originalVibeText]);
    }
}
