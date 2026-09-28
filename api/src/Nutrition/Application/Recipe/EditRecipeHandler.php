<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class EditRecipeHandler implements CommandHandler
{
    public function __construct(private RecipeRepository $recipes, private Compositions $compositions)
    {
    }

    public function __invoke(EditRecipe $command): void
    {
        $recipe = $this->recipes->getVisibleTo($command->recipeId, $command->userId);
        $details = $command->details;
        $ingredients = $details->ingredients();
        $this->compositions->assertUsable($command->userId, $ingredients, $recipe->ingredients());

        $recipe->edit($details->name, $details->servings, $details->cookedWeightGrams, $ingredients);
        $this->recipes->save($recipe);
    }
}
