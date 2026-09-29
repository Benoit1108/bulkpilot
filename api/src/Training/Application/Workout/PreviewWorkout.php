<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramId;

/**
 * What starting this program day would plan (the device keeps it to start offline).
 */
/**
 * @implements Query<WorkoutView>
 */
final readonly class PreviewWorkout implements Query
{
    public function __construct(public UserId $userId, public ProgramId $programId, public int $dayIndex)
    {
    }
}
