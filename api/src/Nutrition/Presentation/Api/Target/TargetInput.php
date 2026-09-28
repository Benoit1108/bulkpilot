<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use App\Nutrition\Domain\Target\MacroTargets;
use Symfony\Component\Validator\Constraints as Assert;

final class TargetInput
{
    /** The user's local day the targets apply from: today, or tomorrow. */
    #[Assert\NotBlank]
    #[Assert\Date]
    public string $effectiveFrom = '';

    #[Assert\NotNull]
    #[Assert\Range(min: MacroTargets::MIN_ENERGY_KCAL, max: MacroTargets::MAX_ENERGY_KCAL)]
    public ?int $energyKcal = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1000)]
    public ?int $proteinGrams = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 1500)]
    public ?int $carbohydrateGrams = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 700)]
    public ?int $fatGrams = null;

    /** The estimate the targets start from, if any (kept to explain them). */
    #[Assert\Valid]
    public ?EstimateInput $estimate = null;
}
