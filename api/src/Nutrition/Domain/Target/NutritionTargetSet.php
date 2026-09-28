<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * "2200 → 2350 kcal, source : coach" (spec §10), with what is needed to rebuild the change.
 */
final readonly class NutritionTargetSet implements DomainEvent
{
    public function __construct(
        public NutritionTargetId $targetId,
        public UserId $userId,
        public \DateTimeImmutable $effectiveFrom,
        public ?MacroTargets $previous,
        public MacroTargets $targets,
        public TargetSource $source,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
