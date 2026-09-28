<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryUserSessionRepository implements UserSessionRepository
{
    /** @var array<string, UserSession> */
    public array $sessions = [];

    public function save(UserSession $session): void
    {
        $this->sessions[$session->id()->value] = $session;
    }

    public function find(UserSessionId $id): ?UserSession
    {
        return $this->sessions[$id->value] ?? null;
    }

    public function activeOf(UserId $userId, \DateTimeImmutable $now): array
    {
        $active = array_values(array_filter(
            $this->sessions,
            static fn (UserSession $session): bool => $session->belongsTo($userId) && $session->isActive($now),
        ));
        usort($active, static fn (UserSession $a, UserSession $b): int => $b->lastSeenAt() <=> $a->lastSeenAt());

        return $active;
    }
}
