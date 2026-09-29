<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the Coach reads of the weigh-ins (ADR-0028), implemented by Progression: the trend, never a
 * single day's weight (spec §65).
 */
interface BodyWeight
{
    /** Where the weight stands at the end of a day, from the weigh-ins up to it. */
    public function reading(UserId $userId, \DateTimeImmutable $day): WeightReading;
}
