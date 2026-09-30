<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the notifications read of the weigh-ins and the weight goal (ADR-0029), implemented by Progression.
 */
interface WeighingReadings
{
    public function day(UserId $userId, \DateTimeImmutable $day): DayWeighing;
}
