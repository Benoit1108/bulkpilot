<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Domain\Food\NutritionFacts;

/**
 * Per 100 g; null when the source does not give the value.
 */
final readonly class Composition
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
        #[ApiProperty(required: true)]
        public ?float $fibre,
        #[ApiProperty(required: true)]
        public ?float $sugars,
        #[ApiProperty(required: true)]
        public ?float $saturatedFat,
        #[ApiProperty(required: true)]
        public ?float $salt,
    ) {
    }

    public static function of(NutritionFacts $facts): self
    {
        return new self($facts->energyKcal, $facts->protein, $facts->carbohydrate, $facts->fat, $facts->fibre, $facts->sugars, $facts->saturatedFat, $facts->salt);
    }
}
