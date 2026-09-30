<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The next day of the active program, for the dashboard's « Démarrer ».
 *
 * @implements Query<NextWorkoutView>
 */
final readonly class GetNextWorkout implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
