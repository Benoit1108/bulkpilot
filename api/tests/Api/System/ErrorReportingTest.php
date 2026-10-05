<?php

declare(strict_types=1);

namespace App\Tests\Api\System;

use App\System\Infrastructure\Monitoring\SentryEventScrubber;
use App\Tests\Support\Api\IdentityApiTestCase;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * What Sentry would receive from real requests (docs/MONITORING.md): its listener sees the
 * exception before the firewall and API Platform turn it into an answer, so the scrubber must
 * recognise the refusals in their original form.
 */
final class ErrorReportingTest extends IdentityApiTestCase
{
    /** @var list<\Throwable> */
    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $dispatcher = self::getContainer()->get('event_dispatcher');
        // Sentry's own listener runs at 128: earlier still, the exception as it sees it.
        $dispatcher->addListener(KernelEvents::EXCEPTION, function (ExceptionEvent $event): void {
            $this->seen[] = $event->getThrowable();
        }, 200);
    }

    public function testRefusalsAndUnreadableRequestsAreNotReported(): void
    {
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);

        $this->registerVerified();
        $this->signIn();
        $this->client->request('PUT', '/api/progression/weigh-ins/2026-10-01', ['headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'], 'body' => '{"weightKg":']);
        self::assertResponseStatusCodeSame(400);

        self::assertCount(2, $this->seen, 'the refused read and the unreadable body');
        $scrubber = new SentryEventScrubber();
        foreach ($this->seen as $exception) {
            self::assertNull($scrubber(Event::createEvent(), EventHint::fromArray(['exception' => $exception])), $exception::class.' would be reported');
        }
    }
}
