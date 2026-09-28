<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

use App\Nutrition\Domain\Exception\InvalidNutritionFacts;

/**
 * Composition per 100 g (or 100 ml, read as grams) of a food.
 */
final readonly class NutritionFacts
{
    public const float REFERENCE_GRAMS = 100.0;
    /** Pure fat is 900 kcal; published tables round up to a few kcal more (sunflower oil: 901). */
    public const float MAX_ENERGY_KCAL = 950.0;

    public function __construct(
        public float $energyKcal,
        public float $protein,
        public float $carbohydrate,
        public float $fat,
        public ?float $fibre = null,
        public ?float $sugars = null,
        public ?float $saturatedFat = null,
        public ?float $salt = null,
    ) {
        if ($energyKcal < 0 || $energyKcal > self::MAX_ENERGY_KCAL) {
            throw InvalidNutritionFacts::because(\sprintf('energy must be between 0 and %d kcal per 100 g', (int) self::MAX_ENERGY_KCAL), 'energyKcal');
        }
        foreach (['protein' => $protein, 'carbohydrate' => $carbohydrate, 'fat' => $fat, 'fibre' => $fibre, 'sugars' => $sugars, 'saturatedFat' => $saturatedFat, 'salt' => $salt] as $name => $grams) {
            if (null !== $grams && ($grams < 0 || $grams > 100)) {
                throw InvalidNutritionFacts::because(\sprintf('%s must be between 0 and 100 g per 100 g', $name), $name);
            }
        }
        // Rounding in published tables can slightly exceed 100 g; 105 leaves room for it, not for typos.
        if ($protein + $carbohydrate + $fat > 105) {
            throw InvalidNutritionFacts::because('protein, carbohydrate and fat cannot exceed 100 g per 100 g', 'macros');
        }
        if (null !== $sugars && $sugars > $carbohydrate + 0.5) {
            throw InvalidNutritionFacts::because('sugars cannot exceed carbohydrate', 'sugars');
        }
        if (null !== $saturatedFat && $saturatedFat > $fat + 0.5) {
            throw InvalidNutritionFacts::because('saturated fat cannot exceed fat', 'saturatedFat');
        }
    }

    public function forQuantity(float $grams): NutrientAmounts
    {
        return $this->reference()->scale($grams / self::REFERENCE_GRAMS);
    }

    public function reference(): NutrientAmounts
    {
        return new NutrientAmounts($this->energyKcal, $this->protein, $this->carbohydrate, $this->fat, $this->fibre);
    }
}
