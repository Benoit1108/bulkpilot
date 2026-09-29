<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\Muscle;

/**
 * @implements Query<list<ExerciseView>>
 */
final readonly class ListExercises implements Query
{
    public function __construct(public UserId $userId, public string $query = '', public ?Muscle $muscle = null)
    {
    }
}
