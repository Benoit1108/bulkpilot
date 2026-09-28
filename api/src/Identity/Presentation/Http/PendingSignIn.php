<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A sign-in whose password was right, waiting for the second factor. Kept server-side in the
 * (not yet authenticated) HTTP session for a few minutes; nothing is granted until the code is valid.
 */
final readonly class PendingSignIn
{
    private const string KEY = 'identity.pending_sign_in';
    private const int LIFETIME_SECONDS = 300;

    public function __construct(private RequestStack $requestStack, private ClockInterface $clock)
    {
    }

    public function start(UserId $userId): void
    {
        $this->requestStack->getSession()->set(self::KEY, [
            'user' => $userId->value,
            'expires' => $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS,
        ]);
    }

    public function userId(): ?UserId
    {
        $pending = $this->requestStack->getSession()->get(self::KEY);
        if (!\is_array($pending) || !\is_string($pending['user'] ?? null) || !\is_int($pending['expires'] ?? null)) {
            return null;
        }
        if ($pending['expires'] < $this->clock->now()->getTimestamp()) {
            $this->clear();

            return null;
        }

        return new UserId($pending['user']);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::KEY);
    }
}
