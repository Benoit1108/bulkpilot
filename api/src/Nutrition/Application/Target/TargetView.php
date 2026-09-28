<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\EnergyEstimate;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\TargetSource;

final readonly class TargetView
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $effectiveFrom,
        public MacroTargets $targets,
        public TargetSource $source,
        public ?EnergyEstimate $estimate,
        public \DateTimeImmutable $setAt,
    ) {
    }

    public static function of(NutritionTarget $target): self
    {
        return new self($target->id()->value, $target->effectiveFrom(), $target->targets(), $target->source(), $target->estimate(), $target->setAt());
    }
}
