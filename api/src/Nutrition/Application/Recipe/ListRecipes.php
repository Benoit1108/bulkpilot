<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<RecipeView>>
 */
final readonly class ListRecipes implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
