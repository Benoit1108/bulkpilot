<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Training\Domain\Workout\ProgramDayRef;
use App\Training\Domain\Workout\WorkoutExercise;

final readonly class WorkoutPlan
{
    /**
     * @param list<WorkoutExercise> $exercises
     */
    public function __construct(public ProgramDayRef $programDay, public array $exercises)
    {
    }
}
