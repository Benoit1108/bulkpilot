<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class FavoriteMealView
{
    /**
     * @param list<FavoriteItemView> $items
     */
    public function __construct(public string $id, public string $name, public array $items, public NutrientAmounts $totals)
    {
    }
}
