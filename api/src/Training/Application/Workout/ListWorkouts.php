<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<WorkoutSummaryView>>
 */
final readonly class ListWorkouts implements Query
{
    public function __construct(public UserId $userId, public int $limit = 20, public ?\DateTimeImmutable $before = null)
    {
    }
}
