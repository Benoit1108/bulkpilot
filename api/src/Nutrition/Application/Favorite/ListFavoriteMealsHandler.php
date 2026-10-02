<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListFavoriteMealsHandler implements QueryHandler
{
    public function __construct(private FavoriteMealRepository $favorites, private FavoriteItems $items)
    {
    }

    /**
     * @return list<FavoriteMealView>
     */
    public function __invoke(ListFavoriteMeals $query): array
    {
        $views = [];
        $favorites = $this->favorites->favoritesOf($query->userId);
        $foods = $this->items->foodsOf($query->userId, $favorites);
        foreach ($favorites as $favorite) {
            $items = [];
            foreach ($favorite->items() as $item) {
                $food = $this->items->snapshot($query->userId, $item, $foods);
                $items[] = new FavoriteItemView($food->name, $item->grams, $food->per100Grams->scale($item->grams / 100), null !== $item->recipeId);
            }
            $views[] = new FavoriteMealView(
                $favorite->id()->value,
                $favorite->name(),
                $items,
                NutrientAmounts::sum(array_map(static fn (FavoriteItemView $item): NutrientAmounts => $item->amounts, $items)),
            );
        }

        return $views;
    }
}
