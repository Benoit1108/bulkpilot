<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class FoodIdType extends UuidType
{
    public const string NAME = 'food_id';

    protected function identifierClass(): string
    {
        return FoodId::class;
    }
}
