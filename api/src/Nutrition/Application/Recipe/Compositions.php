<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeComposition;
use App\Shared\Domain\Identifier\UserId;

/**
 * Loads a recipe's foods (deleted custom ones included) and computes its composition.
 */
final readonly class Compositions
{
    public function __construct(private FoodRepository $foods)
    {
    }

    /**
     * @param list<Ingredient> $ingredients
     *
     * @return array<string, Food> by food id
     *
     * @throws FoodNotFound when an ingredient is not a food the user can use
     */
    public function foodsOf(UserId $userId, array $ingredients): array
    {
        $ids = array_map(static fn (Ingredient $ingredient) => $ingredient->foodId, $ingredients);
        $foods = $this->foods->composing($userId, $ids);
        foreach ($ids as $id) {
            if (!isset($foods[$id->value])) {
                throw FoodNotFound::withId($id->value);
            }
        }

        return $foods;
    }

    /**
     * Ingredients being added must be foods the user can pick today; a deleted custom food may
     * only stay in a recipe that already had it.
     *
     * @param list<Ingredient> $ingredients
     * @param list<Ingredient> $already     the recipe's current ingredients, when editing
     *
     * @throws FoodNotFound
     */
    public function assertUsable(UserId $userId, array $ingredients, array $already = []): void
    {
        $kept = [];
        foreach ($already as $ingredient) {
            $kept[$ingredient->foodId->value] = true;
        }
        $foods = $this->foodsOf($userId, $ingredients);
        foreach ($ingredients as $ingredient) {
            if (!isset($kept[$ingredient->foodId->value]) && !$foods[$ingredient->foodId->value]->isVisibleTo($userId)) {
                throw FoodNotFound::withId($ingredient->foodId->value);
            }
        }
    }

    public function of(Recipe $recipe, UserId $userId): RecipeComposition
    {
        return RecipeComposition::of($recipe, $this->foodsOf($userId, $recipe->ingredients()));
    }
}
