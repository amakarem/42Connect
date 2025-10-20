<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AppAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private ClientRegistry $clientRegistry
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'auth_callback';
    }

    // ⚠ Correct signature
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('forty_two');

        $accessToken = $client->getAccessToken();

        /** @var GenericResourceOwner $userData */
        $userData = $client->fetchUserFromToken($accessToken);
        $email = $userData->toArray()['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email returned from 42 API');
        }

        // Find or create user
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $this->em->persist($user);
            $this->em->flush();
        }

        return new SelfValidatingPassport(new UserBadge($email, fn() => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
