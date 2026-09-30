<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Shared\Application\Notifying\DayEating;
use App\Shared\Application\Notifying\EatingReadings;
use App\Shared\Domain\Identifier\UserId;

final class FakeEatingReadings implements EatingReadings
{
    /** @var array<string, DayEating> by user id */
    public array $days = [];
    /** @var list<string> the days asked, Y-m-d */
    public array $asked = [];

    public function day(UserId $userId, \DateTimeImmutable $day): DayEating
    {
        $this->asked[] = $day->format('Y-m-d');

        return $this->days[$userId->value] ?? new DayEating(0.0, null, null);
    }
}
