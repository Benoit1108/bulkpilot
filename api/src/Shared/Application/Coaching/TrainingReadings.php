<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the Coach reads of the workouts (ADR-0028), implemented by Training.
 */
interface TrainingReadings
{
    /** The sessions finished between two days (both included) and the exercises that stall by the last one. */
    public function between(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): TrainingReading;
}
