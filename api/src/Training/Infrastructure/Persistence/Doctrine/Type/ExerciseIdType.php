<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;
use App\Training\Domain\Exercise\ExerciseId;

final class ExerciseIdType extends UuidType
{
    public const string NAME = 'exercise_id';

    protected function identifierClass(): string
    {
        return ExerciseId::class;
    }
}
