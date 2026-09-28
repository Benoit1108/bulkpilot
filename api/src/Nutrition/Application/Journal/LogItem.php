<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Food\FoodId;

final readonly class LogItem
{
    public function __construct(public FoodId $foodId, public float $grams)
    {
    }
}
