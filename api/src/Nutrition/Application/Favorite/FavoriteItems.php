<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Application\Recipe\Compositions;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Domain\Identifier\UserId;

/**
 * What a favourite's items are today: their foods and recipes, deleted ones of the user included.
 */
final readonly class FavoriteItems
{
    public function __construct(private FoodRepository $foods, private RecipeRepository $recipes, private Compositions $compositions)
    {
    }

    public function snapshot(UserId $userId, FavoriteItem $item): LoggedFood
    {
        if (null !== $item->recipeId) {
            $recipe = $this->recipes->getOwnedBy($item->recipeId, $userId);

            return LoggedFood::snapshotOfRecipe($recipe, $this->compositions->of($recipe, $userId));
        }
        \assert(null !== $item->foodId);
        $food = $this->foods->composing($userId, [$item->foodId])[$item->foodId->value] ?? throw FoodNotFound::withId($item->foodId->value);

        return LoggedFood::snapshotOf($food);
    }
}
