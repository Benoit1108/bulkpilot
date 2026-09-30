<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Shared\Application\Notifying\DayWeighing;
use App\Shared\Application\Notifying\WeighingReadings;
use App\Shared\Domain\Identifier\UserId;

final class FakeWeighingReadings implements WeighingReadings
{
    /** @var array<string, DayWeighing> by user id */
    public array $days = [];

    public function day(UserId $userId, \DateTimeImmutable $day): DayWeighing
    {
        return $this->days[$userId->value] ?? new DayWeighing(false, 0, []);
    }
}
