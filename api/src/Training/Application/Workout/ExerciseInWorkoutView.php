<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Training\Domain\Progression\PersonalRecord;
use App\Training\Domain\Progression\Suggestion;
use App\Training\Domain\Workout\WorkoutExercise;

final readonly class ExerciseInWorkoutView
{
    /**
     * @param string|null                   $note       the user's standing cue for the exercise (current, not a copy)
     * @param array<string, PersonalRecord> $records    the bests before this workout, by kind
     * @param list<PersonalRecord>          $newRecords what this workout beat so far
     */
    public function __construct(
        public int $index,
        public WorkoutExercise $exercise,
        public ?string $note,
        public ?PreviousPerformance $previous,
        public ?Suggestion $suggestion,
        public array $records,
        public array $newRecords,
    ) {
    }
}
