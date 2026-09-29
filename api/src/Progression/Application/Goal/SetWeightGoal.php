<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Progression\Domain\Goal\WeightGoalId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Sets the goal to follow, from the latest weigh-in; the current goal, if any, ends.
 */
final readonly class SetWeightGoal implements Command
{
    /**
     * @param list<float> $milestonesKg
     */
    public function __construct(
        public WeightGoalId $goalId,
        public UserId $userId,
        public float $targetKg,
        public array $milestonesKg,
    ) {
    }
}
