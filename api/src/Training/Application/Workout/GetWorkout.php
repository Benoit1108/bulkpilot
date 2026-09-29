<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Workout\WorkoutId;

/**
 * @implements Query<WorkoutView>
 */
final readonly class GetWorkout implements Query
{
    public function __construct(public UserId $userId, public WorkoutId $workoutId)
    {
    }
}
