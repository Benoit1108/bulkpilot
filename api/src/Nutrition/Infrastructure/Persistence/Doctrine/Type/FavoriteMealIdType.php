<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class FavoriteMealIdType extends UuidType
{
    public const string NAME = 'favorite_meal_id';

    protected function identifierClass(): string
    {
        return FavoriteMealId::class;
    }
}
