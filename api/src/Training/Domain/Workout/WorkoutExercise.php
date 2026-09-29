<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Program\PlannedExercise;

/**
 * An exercise of the workout, as planned when it started (a copy: editing the program or the
 * exercise later changes nothing here). Exercises sharing a block are a superset.
 */
final readonly class WorkoutExercise
{
    public function __construct(
        public ExerciseId $exerciseId,
        public string $name,
        public Measurement $measurement,
        public int $block,
        public PlannedExercise $planned,
        /** Added during the workout rather than planned by the program. */
        public bool $added = false,
    ) {
    }
}
