<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * @implements Query<ExerciseView>
 */
final readonly class GetExercise implements Query
{
    public function __construct(public UserId $userId, public ExerciseId $exerciseId)
    {
    }
}
