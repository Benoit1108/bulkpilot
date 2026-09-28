<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetRecipeHandler implements QueryHandler
{
    public function __construct(private RecipeRepository $recipes, private Compositions $compositions)
    {
    }

    public function __invoke(GetRecipe $query): RecipeView
    {
        $recipe = $this->recipes->getVisibleTo($query->recipeId, $query->userId);

        return RecipeView::of($recipe, $this->compositions->foodsOf($query->userId, $recipe->ingredients()), $query->userId);
    }
}
