<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Recipe;

use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class DeleteRecipe implements Command
{
    public function __construct(public RecipeId $recipeId, public UserId $userId)
    {
    }
}
