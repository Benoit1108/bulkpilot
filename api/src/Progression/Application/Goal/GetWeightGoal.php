<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Progression\Domain\Goal\GoalProgress;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The goal followed and where the user stands on it.
 *
 * @implements Query<GoalProgress>
 */
final readonly class GetWeightGoal implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $today)
    {
    }
}
