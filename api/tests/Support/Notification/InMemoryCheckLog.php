<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Notification\Domain\Check\CheckLog;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryCheckLog implements CheckLog
{
    /** @var array<string, \DateTimeImmutable> */
    public array $checkedAt = [];

    public function takeTurn(UserId $userId, \DateTimeImmutable $now): bool
    {
        $last = $this->checkedAt[$userId->value] ?? null;
        if (null !== $last && $last > $now->modify(\sprintf('-%d minutes', self::INTERVAL_MINUTES))) {
            return false;
        }
        $this->checkedAt[$userId->value] = $now;

        return true;
    }
}
