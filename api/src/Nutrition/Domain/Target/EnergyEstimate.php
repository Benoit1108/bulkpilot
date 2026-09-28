<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

/**
 * The detailed calculation shown to the user (spec §24: BMR → expenditure → surplus/deficit → target).
 */
final readonly class EnergyEstimate
{
    public function __construct(
        public EnergyInputs $inputs,
        public float $basalMetabolicRate,
        public float $activityLevel,
        public float $trainingAllowance,
        public float $totalExpenditure,
        public float $dailyAdjustment,
        public MacroTargets $targets,
    ) {
    }
}
