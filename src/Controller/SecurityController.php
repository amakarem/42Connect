<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect user to 42 OAuth login
        return $clientRegistry->getClient('forty_two')->redirect([], []);
    }

    #[Route('/auth/callback', name: 'auth_callback')]
    public function callback()
    {
        // The AppAuthenticator will handle login logic
    }
}
