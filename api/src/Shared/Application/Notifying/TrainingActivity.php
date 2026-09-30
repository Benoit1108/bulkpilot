<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the notifications read of the training (ADR-0029), implemented by Training.
 */
interface TrainingActivity
{
    /**
     * @param \DateTimeImmutable $dayStart the start of the user's day, in their time zone
     * @param \DateTimeImmutable $since    records of the workouts finished from then on
     */
    public function on(UserId $userId, \DateTimeImmutable $dayStart, \DateTimeImmutable $since): DayTraining;
}
