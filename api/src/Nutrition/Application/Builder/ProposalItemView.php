<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Builder;

use App\Nutrition\Domain\Builder\FoodRole;
use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class ProposalItemView
{
    public function __construct(
        public string $foodId,
        public string $name,
        public FoodRole $role,
        public float $grams,
        public NutrientAmounts $amounts,
    ) {
    }
}
