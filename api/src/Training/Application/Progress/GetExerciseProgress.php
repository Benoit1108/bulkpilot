<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * @implements Query<ExerciseProgressView>
 */
final readonly class GetExerciseProgress implements Query
{
    public function __construct(public UserId $userId, public ExerciseId $exerciseId)
    {
    }
}
