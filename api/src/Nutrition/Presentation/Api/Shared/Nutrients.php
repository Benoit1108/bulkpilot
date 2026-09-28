<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Shared;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * Amounts eaten (or planned): kcal and grams, rounded to one decimal for display.
 */
final readonly class Nutrients
{
    public function __construct(
        #[ApiProperty(required: true)]
        public float $energyKcal,
        #[ApiProperty(required: true)]
        public float $protein,
        #[ApiProperty(required: true)]
        public float $carbohydrate,
        #[ApiProperty(required: true)]
        public float $fat,
        /** Null when none of the foods gives it. */
        #[ApiProperty(required: true)]
        public ?float $fibre,
    ) {
    }

    public static function of(NutrientAmounts $amounts): self
    {
        return new self(
            round($amounts->energyKcal, 1),
            round($amounts->protein, 1),
            round($amounts->carbohydrate, 1),
            round($amounts->fat, 1),
            null === $amounts->fibre ? null : round($amounts->fibre, 1),
        );
    }
}
