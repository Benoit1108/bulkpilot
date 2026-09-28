<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Recipe;

use App\Nutrition\Domain\Exception\InvalidRecipe;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * What a recipe is made of, from the current values of its foods.
 */
final readonly class RecipeComposition
{
    private function __construct(
        public NutrientAmounts $total,
        /** Cooked weight when known, otherwise the sum of the ingredients. */
        public float $totalGrams,
        public float $servingGrams,
        public NutrientAmounts $perServing,
        public NutrientAmounts $per100Grams,
    ) {
    }

    /**
     * @param array<string, Food> $foods by food id, every ingredient's food included
     */
    public static function of(Recipe $recipe, array $foods): self
    {
        $total = NutrientAmounts::zero();
        $rawGrams = 0.0;
        foreach ($recipe->ingredients() as $ingredient) {
            $food = $foods[$ingredient->foodId->value] ?? throw InvalidRecipe::because(\sprintf('unknown ingredient %s', $ingredient->foodId->value));
            $total = $total->add($food->facts()->forQuantity($ingredient->grams));
            $rawGrams += $ingredient->grams;
        }
        $totalGrams = $recipe->cookedWeightGrams() ?? $rawGrams;

        return new self(
            $total,
            $totalGrams,
            $totalGrams / $recipe->servings(),
            $total->scale(1 / $recipe->servings()),
            $total->scale(100 / $totalGrams),
        );
    }
}
