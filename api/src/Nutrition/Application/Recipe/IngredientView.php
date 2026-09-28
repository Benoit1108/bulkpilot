<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class IngredientView
{
    public function __construct(
        public string $foodId,
        public string $name,
        public FoodState $state,
        public float $grams,
        public NutrientAmounts $amounts,
        /** False for a custom food deleted since: still counted, no longer searchable. */
        public bool $available,
    ) {
    }
}
