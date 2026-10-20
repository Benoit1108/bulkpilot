<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Food\FoodId;

/**
 * A food the user logged lately, with the quantity of its latest entry: logging it again in one
 * tap repeats that quantity.
 */
final readonly class RecentFood
{
    public function __construct(public FoodId $foodId, public float $lastGrams)
    {
    }
}
