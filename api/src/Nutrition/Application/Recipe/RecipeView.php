<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeComposition;
use App\Shared\Domain\Identifier\UserId;

final readonly class RecipeView
{
    /**
     * @param list<IngredientView> $ingredients
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $servings,
        public ?float $cookedWeightGrams,
        public array $ingredients,
        public RecipeComposition $composition,
    ) {
    }

    /**
     * @param array<string, Food> $foods
     */
    public static function of(Recipe $recipe, array $foods, UserId $viewer): self
    {
        $ingredients = [];
        foreach ($recipe->ingredients() as $ingredient) {
            $food = $foods[$ingredient->foodId->value];
            $ingredients[] = new IngredientView(
                $food->id()->value,
                $food->name(),
                $food->state(),
                $ingredient->grams,
                $food->facts()->forQuantity($ingredient->grams),
                $food->isVisibleTo($viewer),
            );
        }

        return new self($recipe->id()->value, $recipe->name(), $recipe->servings(), $recipe->cookedWeightGrams(), $ingredients, RecipeComposition::of($recipe, $foods));
    }
}
