<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The session cookie has a fixed Max-Age (30 days) set when the session starts. Once a day, a
 * signed-in device gets a fresh session id and cookie, so the 30 days count from the last use,
 * not from the sign-in — and the id rotates regularly.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 10)]
final readonly class SessionCookieRefresher
{
    private const string KEY = 'identity.cookie_refreshed_at';
    private const int INTERVAL_SECONDS = 86400;

    public function __construct(private Security $security, private ClockInterface $clock)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession() || !$request->getSession()->isStarted()
            || !$this->security->getUser() instanceof SecurityUser) {
            return;
        }

        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();
        $last = $session->get(self::KEY);
        if (\is_int($last) && $now - $last < self::INTERVAL_SECONDS) {
            return;
        }

        $session->set(self::KEY, $now);
        if (\is_int($last)) {
            $session->migrate();
        }
    }
}
