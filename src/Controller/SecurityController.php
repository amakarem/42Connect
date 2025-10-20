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

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // This code will never be executed.
        // Symfony will intercept this route and handle logout automatically.
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
