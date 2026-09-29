<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Workout\WorkoutId;

/**
 * A workout started by mistake: gone from history, previous performances and records.
 */
final readonly class DiscardWorkout implements Command
{
    public function __construct(public WorkoutId $workoutId, public UserId $userId)
    {
    }
}
