<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\AppAuthenticator;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to 42 login
        return $clientRegistry->getClient('forty_two')->redirect([], []);
    }

    #[Route('/auth/callback', name: 'auth_callback')]
    public function connectCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $authenticator,
        AppAuthenticator $appAuthenticator
    ) {
        $client = $clientRegistry->getClient('forty_two');
        $userDetails = $client->fetchUser();

        $email = $userDetails->toArray()['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email found from 42 API');
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
        }

        return $authenticator->authenticateUser($user, $appAuthenticator, $request);
    }
}
