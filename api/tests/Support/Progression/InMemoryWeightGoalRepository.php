<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryWeightGoalRepository implements WeightGoalRepository
{
    /** @var array<string, WeightGoal> */
    public array $goals = [];

    public function save(WeightGoal $goal): void
    {
        $this->goals[$goal->id()->value] = $goal;
    }

    public function current(UserId $userId): ?WeightGoal
    {
        foreach ($this->goals as $goal) {
            if ($goal->ownerId()->equals($userId) && null === $goal->endedAt()) {
                return $goal;
            }
        }

        return null;
    }
}
