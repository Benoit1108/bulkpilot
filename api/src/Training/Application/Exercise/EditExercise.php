<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * Programs and past workouts show the new name; workouts keep the sets they recorded.
 */
final readonly class EditExercise implements Command
{
    public function __construct(public ExerciseId $exerciseId, public UserId $userId, public ExerciseDetails $details)
    {
    }
}
