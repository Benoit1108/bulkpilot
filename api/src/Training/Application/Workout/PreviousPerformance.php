<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Training\Domain\Workout\WorkoutSet;

/**
 * What was done on this exercise last time (spec §43).
 */
final readonly class PreviousPerformance
{
    /**
     * @param list<WorkoutSet> $sets
     */
    public function __construct(public \DateTimeImmutable $date, public array $sets)
    {
    }
}
