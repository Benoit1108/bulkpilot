<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Sets new daily targets from a day on. With the estimate they came from, they are recorded as
 * "estimate" when unchanged and as "manual" when the user adjusted them (the estimate is kept to
 * explain the starting point).
 */
final readonly class SetTargets implements Command
{
    public function __construct(
        public NutritionTargetId $targetId,
        public UserId $userId,
        public \DateTimeImmutable $effectiveFrom,
        public MacroTargets $targets,
        public ?EstimateRequest $estimate,
    ) {
    }
}
