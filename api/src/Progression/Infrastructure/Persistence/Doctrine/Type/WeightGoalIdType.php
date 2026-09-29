<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine\Type;

use App\Progression\Domain\Goal\WeightGoalId;
use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;

final class WeightGoalIdType extends UuidType
{
    public const string NAME = 'weight_goal_id';

    protected function identifierClass(): string
    {
        return WeightGoalId::class;
    }
}
