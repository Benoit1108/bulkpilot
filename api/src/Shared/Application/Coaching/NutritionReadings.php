<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

use App\Shared\Domain\Identifier\UserId;

/**
 * What the Coach reads of the nutrition journal (ADR-0028), implemented by Nutrition. Figures
 * only: the Coach judges them, Nutrition keeps how they are computed.
 */
interface NutritionReadings
{
    /**
     * @return list<DayIntake> the days with anything logged between two days (both included), the oldest first
     */
    public function intake(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** The target in force on that day, if any. */
    public function targetOn(UserId $userId, \DateTimeImmutable $day): ?TargetReading;
}
