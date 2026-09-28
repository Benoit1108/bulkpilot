<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class RecipeIdType extends UuidType
{
    public const string NAME = 'recipe_id';

    protected function identifierClass(): string
    {
        return RecipeId::class;
    }
}
