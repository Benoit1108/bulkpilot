<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Recipe\Ingredient;

final readonly class RecipeDetails
{
    /**
     * @param list<array{foodId: string, grams: float}> $ingredients
     */
    public function __construct(
        public string $name,
        public int $servings,
        public ?float $cookedWeightGrams,
        public array $ingredients,
    ) {
    }

    /**
     * @return list<Ingredient>
     */
    public function ingredients(): array
    {
        return array_map(static fn (array $item): Ingredient => new Ingredient(new FoodId($item['foodId']), $item['grams']), $this->ingredients);
    }
}
