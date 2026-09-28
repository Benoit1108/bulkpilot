<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class FavoriteItemView
{
    public function __construct(public string $name, public float $grams, public NutrientAmounts $amounts, public bool $recipe)
    {
    }
}
