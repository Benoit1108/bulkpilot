<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the notifications read of a day's journal (ADR-0029), implemented by Nutrition.
 */
interface EatingReadings
{
    /** The user's day: what was eaten, against the target in force, and when something was last logged. */
    public function day(UserId $userId, \DateTimeImmutable $day): DayEating;
}
