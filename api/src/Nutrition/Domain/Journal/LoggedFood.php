<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeComposition;
use App\Nutrition\Domain\Recipe\RecipeId;

/**
 * What was eaten, frozen at logging time: editing or deleting the food or recipe (or a new
 * CIQUAL release) never rewrites the journal. It came from a food or from a recipe; the id is
 * kept for "recent foods" and favourites only.
 */
final readonly class LoggedFood
{
    /**
     * @param NutrientAmounts $per100Grams composition at logging time
     */
    public function __construct(
        public ?FoodId $foodId,
        public string $name,
        public FoodState $state,
        public NutrientAmounts $per100Grams,
        public ?RecipeId $recipeId = null,
    ) {
    }

    public static function snapshotOf(Food $food): self
    {
        $name = null === $food->brand() ? $food->name() : \sprintf('%s (%s)', $food->name(), $food->brand());

        return new self($food->id(), $name, $food->state(), $food->facts()->reference());
    }

    public static function snapshotOfRecipe(Recipe $recipe, RecipeComposition $composition): self
    {
        return new self(null, $recipe->name(), FoodState::NotApplicable, $composition->per100Grams, $recipe->id());
    }
}
