<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\Health;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Uptime monitors and readiness probes read the status code, not the body:
 * a degraded report is served as 503 while keeping the detailed body.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class DegradedHealthStatusListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $data = $event->getRequest()->attributes->get('data');

        if ($data instanceof Health && 'degraded' === $data->status) {
            $event->getResponse()->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
