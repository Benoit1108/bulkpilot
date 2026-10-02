<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The API answers data, never a page: its responses may load nothing and be framed nowhere
 * (spec §101), should one ever be opened as a document, HTML error pages included. The page of
 * the app has its own policy (Caddyfile). Only the API docs, served with debug on (dev, test), are
 * left alone: Swagger UI and ReDoc run scripts. With debug on, Symfony itself also drops the policy
 * of error responses, for its error pages.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ApiContentSecurityPolicy
{
    public const string POLICY = "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
    private const string DOCS = '/api/docs';

    public function __construct(#[Autowire('%kernel.debug%')] private bool $debug)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest()
            || !str_starts_with($path, '/api')
            || $response->headers->has('Content-Security-Policy')
            || ($this->debug && str_starts_with($path, self::DOCS))) {
            return;
        }
        $response->headers->set('Content-Security-Policy', self::POLICY);
    }
}
