<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListRecipesHandler implements QueryHandler
{
    public function __construct(private RecipeRepository $recipes, private FoodRepository $foods)
    {
    }

    /**
     * @return list<RecipeView>
     */
    public function __invoke(ListRecipes $query): array
    {
        $recipes = $this->recipes->recipesOf($query->userId);
        // One query for the foods of every recipe.
        $ids = [];
        foreach ($recipes as $recipe) {
            foreach ($recipe->ingredients() as $ingredient) {
                $ids[$ingredient->foodId->value] = $ingredient->foodId;
            }
        }
        $foods = $this->foods->composing($query->userId, array_values($ids));

        $views = [];
        foreach ($recipes as $recipe) {
            $complete = array_all($recipe->ingredients(), static fn (Ingredient $ingredient): bool => isset($foods[$ingredient->foodId->value]));
            if ($complete) {
                $views[] = RecipeView::of($recipe, $foods, $query->userId);
            }
        }

        return $views;
    }
}
