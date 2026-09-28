<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class NutritionTargetIdType extends UuidType
{
    public const string NAME = 'nutrition_target_id';

    protected function identifierClass(): string
    {
        return NutritionTargetId::class;
    }
}
