<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<RecipeView>
 */
final readonly class GetRecipe implements Query
{
    public function __construct(public UserId $userId, public RecipeId $recipeId)
    {
    }
}
