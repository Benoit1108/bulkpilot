<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class CreateRecipeHandler implements CommandHandler
{
    public function __construct(private RecipeRepository $recipes, private Compositions $compositions, private ClockInterface $clock)
    {
    }

    public function __invoke(CreateRecipe $command): void
    {
        $details = $command->details;
        $ingredients = $details->ingredients();
        $this->compositions->assertUsable($command->userId, $ingredients);

        $this->recipes->save(Recipe::create($command->recipeId, $command->userId, $details->name, $details->servings, $details->cookedWeightGrams, $ingredients, $this->clock->now()));
    }
}
