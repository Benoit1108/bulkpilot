<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Workout\WorkoutId;

/**
 * Starts a workout, from a program day (the exercises are copied from the version the device
 * saw) or empty. Sending it again changes nothing: the device may retry after a lost connection.
 */
final readonly class StartWorkout implements Command
{
    public function __construct(public WorkoutId $workoutId, public UserId $userId, public \DateTimeImmutable $startedAt, public ?ProgramId $programId = null, public ?int $versionNumber = null, public int $dayIndex = 0)
    {
    }
}
