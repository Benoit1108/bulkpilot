<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<WorkoutView|null>
 */
final readonly class GetWorkoutInProgress implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
