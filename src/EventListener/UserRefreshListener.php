<?php
// src/EventListener/UserRefreshListener.php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

class UserRefreshListener
{
    private Security $security;
    private RouterInterface $router;

    public function __construct(Security $security, RouterInterface $router)
    {
        $this->security = $security;
        $this->router = $router;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only run on main request (page refresh)
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        // Only check if user is logged in
        if (!$user) {
            return;
        }

        if (method_exists($user, 'getUpdatedAt') && $user->getUpdatedAt()) {
            $now = new \DateTime();
            $interval = $now->getTimestamp() - $user->getUpdatedAt()->getTimestamp();

            // If older than 5 minutes, redirect to /login
            if ($interval > 300) {
                $response = new RedirectResponse($this->router->generate('app_login'));
                $event->setResponse($response);
            }
        }
    }
}
