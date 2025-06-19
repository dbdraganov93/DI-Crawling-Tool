<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class TwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security, private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->security->getToken();
        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$token || !$token->getUser() instanceof User) {
            return;
        }

        $user = $token->getUser();
        if ($user->isTwoFactorEnabled() && !$session->get('2fa_verified')) {
            if ($request->attributes->get('_route') !== 'app_2fa_verify') {
                $session->set('2fa_user_id', $user->getId());
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_verify')));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
