<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\WorkoutId;

final readonly class RemoveSet implements Command
{
    public function __construct(public WorkoutId $workoutId, public UserId $userId, public SetId $setId)
    {
    }
}
