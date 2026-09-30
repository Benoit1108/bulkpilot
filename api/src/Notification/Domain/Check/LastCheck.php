<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Shared\Domain\Identifier\UserId;

/**
 * When an account's checks last ran (the row {@see CheckLog} takes its turns on).
 */
class LastCheck
{
    public function __construct(private readonly UserId $ownerId, private \DateTimeImmutable $checkedAt)
    {
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function checkedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }
}
