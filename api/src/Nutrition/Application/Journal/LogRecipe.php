<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Logs a quantity of a dish: grams of the (cooked) recipe, e.g. servings × serving weight.
 */
final readonly class LogRecipe implements Command
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $userId,
        public RecipeId $recipeId,
        public \DateTimeImmutable $day,
        public MealSlot $slot,
        public float $grams,
    ) {
    }
}
