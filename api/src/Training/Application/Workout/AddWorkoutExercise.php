<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Workout\WorkoutId;

/**
 * An exercise done on top of the plan (or in a free workout), in its own block.
 */
final readonly class AddWorkoutExercise implements Command
{
    public function __construct(public WorkoutId $workoutId, public UserId $userId, public int $index, public ExerciseId $exerciseId)
    {
    }
}
