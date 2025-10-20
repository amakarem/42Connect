<?php
namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AppAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Only handle requests to the OAuth callback route
     */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'auth_callback';
    }

    /**
     * Fetch OAuth token and return Passport
     */
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('forty_two');

        // Get access token
        $accessToken = $client->getAccessToken();

        // Fetch user data from 42 API
        $userData = $client->fetchUserFromToken($accessToken)->toArray();
        $email = $userData['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email returned from 42 API');
        }

        // Return passport with UserBadge
        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($userData, $email) {
                // Check if user exists
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

                // Create user if not exists
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);
                    $this->em->persist($user);
                }

                // Update fields every login
                $user->setIntraLogin($userData['login'] ?? null);
                $user->setUsualFullName($userData['usual_full_name'] ?? null);
                $user->setDisplayName($userData['displayname'] ?? null);
                $user->setKind($userData['kind'] ?? null);
                $user->setImage($userData['image']['link'] ?? null);
                $user->setLocation($userData['location'] ?? null);
                if (isset($userData['projects_users']) && is_array($userData['projects_users'])) {
                    $projects = [];
                    foreach ($userData['projects_users'] as $project) {
                        $projects[] = ["name" =>$project["project"]["name"], "status" => $project["status"], "updated_at" => $project["updated_at"], "final_mark" => $project["final_mark"]];
                    }
                    $user->setProjects($projects);
                }
                $user->setCampus($userData['campus'] ?? []);

                $this->em->flush();

                return $user;
            })
        );
    }

    /**
     * Called after successful authentication
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect to home or dashboard
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    /**
     * Called on authentication failure
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Redirect back to login with optional error message
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
