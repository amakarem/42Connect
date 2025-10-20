<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $user->setReadyToHelp(!$user->getReadyToHelp());
        $em->flush();

        // If using AJAX, return JSON
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'readyToHelp' => $user->getReadyToHelp()
            ]);
        }

        // Otherwise redirect back to home
        return $this->redirectToRoute('app_home');
    }
}
