<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Recipe;

use App\Nutrition\Domain\Exception\InvalidRecipe;
use App\Nutrition\Domain\Food\FoodId;

/**
 * A weighed food of a recipe, raw or cooked as the food says ("riz cru, 300 g").
 */
final readonly class Ingredient
{
    public const float MAX_GRAMS = 5000.0;

    public float $grams;

    public function __construct(public FoodId $foodId, float $grams)
    {
        if ($grams <= 0 || $grams > self::MAX_GRAMS) {
            throw InvalidRecipe::because(\sprintf('an ingredient weighs between 0 and %d g', (int) self::MAX_GRAMS));
        }
        $this->grams = round($grams, 1);
    }
}
