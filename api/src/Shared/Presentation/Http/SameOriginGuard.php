<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF defence for the cookie-authenticated API (ADR-0008), on top of SameSite=Lax:
 * a state-changing request coming from another site is rejected before anything runs.
 *
 * Browsers always send Origin (and Sec-Fetch-Site) on cross-site fetches; requests without
 * them are non-browser clients, which carry no ambient cookie to abuse.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final readonly class SameOriginGuard
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || \in_array($request->getMethod(), self::SAFE_METHODS, true)
            || !str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if (null !== $fetchSite && !\in_array($fetchSite, ['same-origin', 'none'], true)) {
            throw new AccessDeniedHttpException('Cross-site request rejected.');
        }

        $origin = $request->headers->get('Origin');
        if (null !== $origin && $origin !== $request->getSchemeAndHttpHost()) {
            throw new AccessDeniedHttpException('Cross-origin request rejected.');
        }
    }
}
