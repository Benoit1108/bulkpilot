<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

use App\Shared\Domain\Identifier\UserId;

interface WeightGoalRepository
{
    public function save(WeightGoal $goal): void;

    /** The goal being followed: the last one set, not ended. */
    public function current(UserId $userId): ?WeightGoal;
}
