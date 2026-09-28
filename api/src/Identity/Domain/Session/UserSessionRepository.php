<?php

declare(strict_types=1);

namespace App\Identity\Domain\Session;

use App\Shared\Domain\Identifier\UserId;

interface UserSessionRepository
{
    public function save(UserSession $session): void;

    public function find(UserSessionId $id): ?UserSession;

    /**
     * Not revoked and not idle for longer than UserSession::IDLE_LIFETIME_SECONDS.
     *
     * @return list<UserSession> most recently active first
     */
    public function activeOf(UserId $userId, \DateTimeImmutable $now): array;
}
