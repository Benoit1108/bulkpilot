<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Application\Recipe\Compositions;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Food\Food;
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

    /**
     * @param array<string, Food>|null $foods the items' foods read beforehand ({@see foodsOf}), by id
     */
    public function snapshot(UserId $userId, FavoriteItem $item, ?array $foods = null): LoggedFood
    {
        if (null !== $item->recipeId) {
            $recipe = $this->recipes->getOwnedBy($item->recipeId, $userId);

            return LoggedFood::snapshotOfRecipe($recipe, $this->compositions->of($recipe, $userId));
        }
        \assert(null !== $item->foodId);
        $foods ??= $this->foods->composing($userId, [$item->foodId]);
        $food = $foods[$item->foodId->value] ?? throw FoodNotFound::withId($item->foodId->value);

        return LoggedFood::snapshotOf($food);
    }

    /**
     * The foods of every item of these favourites, in one read.
     *
     * @param list<FavoriteMeal> $favorites
     *
     * @return array<string, Food> by id
     */
    public function foodsOf(UserId $userId, array $favorites): array
    {
        $ids = [];
        foreach ($favorites as $favorite) {
            foreach ($favorite->items() as $item) {
                if (null !== $item->foodId) {
                    $ids[$item->foodId->value] = $item->foodId;
                }
            }
        }

        return [] === $ids ? [] : $this->foods->composing($userId, array_values($ids));
    }
}
