<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;
use App\Training\Domain\Workout\WorkoutId;

final class WorkoutIdType extends UuidType
{
    public const string NAME = 'workout_id';

    protected function identifierClass(): string
    {
        return WorkoutId::class;
    }
}
