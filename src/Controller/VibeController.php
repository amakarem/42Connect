<?php

namespace App\Controller;

use App\Entity\Vibe;
use App\Form\VibeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VibeController extends AbstractController
{
    #[Route('/vibe/fill', name: 'vibe_fill')]
    public function fill(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $vibe = $user->getVibe() ?? new Vibe();
        $vibe->setUser($user);

        $form = $this->createForm(VibeType::class, $vibe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($vibe);
            $em->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('vibe/fill.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
