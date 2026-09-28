<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Application\Recipe\Compositions;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class LogRecipeHandler implements CommandHandler
{
    public function __construct(private RecipeRepository $recipes, private Compositions $compositions, private JournalWriter $journal)
    {
    }

    public function __invoke(LogRecipe $command): void
    {
        $recipe = $this->recipes->getVisibleTo($command->recipeId, $command->userId);
        $food = LoggedFood::snapshotOfRecipe($recipe, $this->compositions->of($recipe, $command->userId));

        $this->journal->log($command->entryId, $command->userId, $command->day, $command->slot, $food, $command->grams);
    }
}
