<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class DeleteRecipeHandler implements CommandHandler
{
    public function __construct(private RecipeRepository $recipes, private ClockInterface $clock)
    {
    }

    public function __invoke(DeleteRecipe $command): void
    {
        $recipe = $this->recipes->getVisibleTo($command->recipeId, $command->userId);
        $recipe->delete($this->clock->now());
        $this->recipes->save($recipe);
    }
}
