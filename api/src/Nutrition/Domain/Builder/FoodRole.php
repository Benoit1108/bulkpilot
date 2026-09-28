<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Builder;

use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Food\NutritionFacts;

/**
 * The part a food plays in a meal, from where its calories come from.
 */
enum FoodRole: string
{
    case Protein = 'protein';
    case Carbohydrate = 'carbohydrate';
    case Fat = 'fat';
    /** Fruit, dairy, vegetables…: a bit of everything. */
    case Side = 'side';

    /** Below this, a food (water, broth, spices) does not help reach a target. */
    public const float MIN_KCAL_PER_100G = 20.0;

    public static function of(NutritionFacts $facts): ?self
    {
        $protein = NutrientAmounts::KCAL_PER_GRAM_PROTEIN * $facts->protein;
        $carbohydrate = NutrientAmounts::KCAL_PER_GRAM_CARBOHYDRATE * $facts->carbohydrate;
        $fat = NutrientAmounts::KCAL_PER_GRAM_FAT * $facts->fat;
        $total = $protein + $carbohydrate + $fat;
        if ($facts->energyKcal < self::MIN_KCAL_PER_100G || $total <= 0) {
            return null;
        }

        return match (true) {
            $protein / $total >= 0.35 => self::Protein,
            $fat / $total >= 0.7 => self::Fat,
            $carbohydrate / $total >= 0.55 => self::Carbohydrate,
            default => self::Side,
        };
    }

    /**
     * Sensible amounts in one meal: nobody eats 400 g of oil or 5 g of rice.
     *
     * @return array{float, float} min and max grams
     */
    public function gramRange(): array
    {
        return match ($this) {
            self::Protein => [50.0, 400.0],
            self::Carbohydrate => [40.0, 500.0],
            self::Fat => [5.0, 40.0],
            self::Side => [50.0, 300.0],
        };
    }

    /** Oils are weighed to the gram, the rest to 5 g. */
    public function rounding(): float
    {
        return self::Fat === $this ? 1.0 : 5.0;
    }
}
