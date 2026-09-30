<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Shared\Application\Notifying\CoachReports;
use App\Shared\Domain\Identifier\UserId;

final class FakeCoachReports implements CoachReports
{
    /** @var array<string, \DateTimeImmutable> by user id */
    public array $weeks = [];

    public function latestWeek(UserId $userId): ?\DateTimeImmutable
    {
        return $this->weeks[$userId->value] ?? null;
    }
}
