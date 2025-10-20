<?php
// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            // Anonymous user: show welcome page
            return $this->render('home/welcome.html.twig');
        }

        // Logged-in user: show dashboard
        return $this->render('home/dashboard.html.twig', [
            'user' => $user
        ]);
    }
}
