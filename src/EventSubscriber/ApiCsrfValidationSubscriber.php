<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ApiCsrfValidationSubscriber implements EventSubscriberInterface
{
    private $csrfTokenManager;
    private $newToken;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // no validation needed on safe methods
        if ($request->isMethodSafe(false)) {
            return;
        }

        if (!$request->attributes->get('_is_api')) {
            return;
        }

        if ($request->headers->get('Content-Type') != 'application/json') {
            $response = new JsonResponse([
                'message' => 'Invalid Content-Type'
            ], 415);

            $event->setResponse($response);

            return;
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('api', $csrfToken))) {
            throw new AccessDeniedException('Invalid CSRF Token');
        }

        // regenerate the csrf id
        $this->newToken = $this->csrfTokenManager->refreshToken('api');
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if ($this->newToken) {
            $event->getResponse()->headers->set('X-CSRF-TOKEN', $this->newToken);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
           'kernel.request' => 'onKernelRequest',
            'kernel.response' => 'onKernelResponse'
        ];
    }
}
