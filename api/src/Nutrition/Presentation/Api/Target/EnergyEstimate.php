<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Nutrition\Domain\Target\EnergyEstimate as Estimate;

/**
 * The calculation shown step by step: BMR → expenditure → surplus/deficit → targets.
 */
#[ApiResource(
    shortName: 'EnergyEstimate',
    operations: [
        new Post(
            uriTemplate: '/nutrition/targets/estimate',
            status: 200,
            description: 'Computes suggested daily targets (Mifflin-St Jeor); nothing is saved.',
            input: EstimateInput::class,
            processor: EnergyEstimateProcessor::class,
        ),
    ],
)]
final readonly class EnergyEstimate
{
    public function __construct(
        #[ApiProperty(required: true)]
        public int $age,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['female', 'male']])]
        public string $sex,
        #[ApiProperty(required: true)]
        public int $heightCm,
        #[ApiProperty(required: true)]
        public float $weightKg,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['sedentary', 'light', 'active', 'very_active']])]
        public string $lifestyle,
        #[ApiProperty(required: true)]
        public int $trainingSessionsPerWeek,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['bulk', 'maintain', 'cut']])]
        public string $goal,
        #[ApiProperty(required: true)]
        public float $weeklyWeightChangeKg,
        #[ApiProperty(required: true)]
        public int $basalMetabolicRate,
        #[ApiProperty(required: true)]
        public float $activityLevel,
        #[ApiProperty(required: true)]
        public float $trainingAllowance,
        #[ApiProperty(required: true)]
        public int $totalExpenditure,
        #[ApiProperty(required: true)]
        public int $dailyAdjustment,
        #[ApiProperty(required: true)]
        public int $energyKcal,
        #[ApiProperty(required: true)]
        public int $proteinGrams,
        #[ApiProperty(required: true)]
        public int $carbohydrateGrams,
        #[ApiProperty(required: true)]
        public int $fatGrams,
    ) {
    }

    public static function of(Estimate $estimate): self
    {
        $inputs = $estimate->inputs;
        $targets = $estimate->targets;

        return new self(
            $inputs->age,
            $inputs->sex->value,
            $inputs->heightCm,
            $inputs->weightKg,
            $inputs->lifestyle->value,
            $inputs->trainingSessionsPerWeek,
            $inputs->goal->value,
            $inputs->weeklyWeightChangeKg,
            (int) round($estimate->basalMetabolicRate),
            $estimate->activityLevel,
            $estimate->trainingAllowance,
            (int) round($estimate->totalExpenditure),
            (int) round($estimate->dailyAdjustment),
            $targets->energyKcal,
            $targets->proteinGrams,
            $targets->carbohydrateGrams,
            $targets->fatGrams,
        );
    }
}
