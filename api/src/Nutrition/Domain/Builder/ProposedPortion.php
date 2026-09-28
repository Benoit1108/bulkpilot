<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Builder;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class ProposedPortion
{
    public function __construct(public Food $food, public FoodRole $role, public float $grams)
    {
    }

    public function amounts(): NutrientAmounts
    {
        return $this->food->facts()->forQuantity($this->grams);
    }
}
