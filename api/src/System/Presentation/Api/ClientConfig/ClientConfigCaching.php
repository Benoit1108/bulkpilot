<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\ClientConfig;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The same for everyone and nothing personal: cacheable for a few minutes, even though the
 * firewall looked at the session (which otherwise makes every response private).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ClientConfigCaching
{
    public const int MAX_AGE = 300;

    public function __invoke(ResponseEvent $event): void
    {
        if ('_api_/client-config_get' !== $event->getRequest()->attributes->get('_route') || !$event->getResponse()->isSuccessful()) {
            return;
        }
        $response = $event->getResponse();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->headers->remove('Set-Cookie');
    }
}
