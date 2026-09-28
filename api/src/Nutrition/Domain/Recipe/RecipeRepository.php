<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Recipe;

use App\Nutrition\Domain\Exception\RecipeNotFound;
use App\Shared\Domain\Identifier\UserId;

interface RecipeRepository
{
    public function save(Recipe $recipe): void;

    /**
     * @throws RecipeNotFound when it does not exist, is deleted or is someone else's
     */
    public function getVisibleTo(RecipeId $id, UserId $userId): Recipe;

    /**
     * Also a deleted one of the user's: favourites keep working (see Recipe::delete()).
     *
     * @throws RecipeNotFound
     */
    public function getOwnedBy(RecipeId $id, UserId $userId): Recipe;

    /**
     * @return list<Recipe> the user's recipes, by name
     */
    public function recipesOf(UserId $userId): array;
}
