<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Domain\Identifier\UserId;

final readonly class SessionTerminator
{
    public function __construct(private UserSessionRepository $sessions)
    {
    }

    public function terminateAll(UserId $userId, ?UserSessionId $except, \DateTimeImmutable $now): void
    {
        foreach ($this->sessions->activeOf($userId, $now) as $session) {
            if (null !== $except && $session->id()->equals($except)) {
                continue;
            }
            $session->revoke($now);
            $this->sessions->save($session);
        }
    }
}
