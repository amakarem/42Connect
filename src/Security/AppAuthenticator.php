<?php
namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AppAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function authenticate(Request $request): SelfValidatingPassport
    {
        // OAuth token is fetched automatically by KnpU bundle
        $accessToken = $this->clientRegistry->getClient('forty_two')->getAccessToken();

        $userData = $this->clientRegistry->getClient('forty_two')->fetchUserFromToken($accessToken)->toArray();
        $email = $userData['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email returned from 42 API');
        }

        return new SelfValidatingPassport(
            new UserBadge($email)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        $userData = $token->getUser()->getUserData(); // Or fetch token again if needed
        $email = $token->getUser()->getUserIdentifier();

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
        }

        // Update fields only after successful authentication
        $user->setIntraLogin($userData['login'] ?? null);
        $user->setUsualFullName($userData['usual_full_name'] ?? null);
        $user->setDisplayName($userData['displayname'] ?? null);
        $user->setKind($userData['kind'] ?? null);
        $user->setImage($userData['image']['link'] ?? null);
        $user->setLocation($userData['location'] ?? null);
        $user->setProjects($userData['projects'] ?? []);
        $user->setCampus($userData['campus'] ?? []);

        $this->em->flush();

        // Redirect to homepage or dashboard
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception)
    {
        // Optional: redirect to login with error
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
