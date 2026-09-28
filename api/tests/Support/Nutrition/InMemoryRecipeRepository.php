<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Exception\RecipeNotFound;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryRecipeRepository implements RecipeRepository
{
    /** @var array<string, Recipe> */
    public array $recipes = [];

    public function save(Recipe $recipe): void
    {
        $this->recipes[$recipe->id()->value] = $recipe;
    }

    public function getVisibleTo(RecipeId $id, UserId $userId): Recipe
    {
        $recipe = $this->getOwnedBy($id, $userId);
        if (!$recipe->isVisibleTo($userId)) {
            throw RecipeNotFound::withId($id->value);
        }

        return $recipe;
    }

    public function getOwnedBy(RecipeId $id, UserId $userId): Recipe
    {
        $recipe = $this->recipes[$id->value] ?? null;
        if (null === $recipe || !$recipe->belongsTo($userId)) {
            throw RecipeNotFound::withId($id->value);
        }

        return $recipe;
    }

    public function recipesOf(UserId $userId): array
    {
        $recipes = array_values(array_filter($this->recipes, static fn (Recipe $recipe): bool => $recipe->isVisibleTo($userId)));
        usort($recipes, static fn (Recipe $a, Recipe $b): int => mb_strtolower($a->name()) <=> mb_strtolower($b->name()));

        return $recipes;
    }
}
