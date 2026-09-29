<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Training\Domain\Workout\ProgramDayRef;
use App\Training\Domain\Workout\WorkoutSet;

/**
 * A workout, or the plan of one not started yet (no id, no sets), with what the user needs at
 * each exercise: the cue and what was done last time.
 */
final readonly class WorkoutView
{
    /**
     * @param list<ExerciseInWorkoutView> $exercises
     * @param list<WorkoutSet>            $sets
     */
    public function __construct(
        public ?string $id,
        public ?ProgramDayRef $programDay,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public array $exercises,
        public array $sets,
    ) {
    }
}
