<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Favorite;

use App\Nutrition\Domain\Exception\InvalidFavoriteMeal;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Recipe\RecipeId;

/**
 * A quantity of a food or of a recipe (exactly one of the two).
 */
final readonly class FavoriteItem
{
    public function __construct(public ?FoodId $foodId, public ?RecipeId $recipeId, public float $grams)
    {
        if ((null === $foodId) === (null === $recipeId)) {
            throw InvalidFavoriteMeal::because('an item is a food or a recipe');
        }
        if ($grams <= 0 || $grams > JournalEntry::MAX_GRAMS) {
            throw InvalidFavoriteMeal::because('an item weighs between 0 and 5000 g');
        }
    }
}
