<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\UuidType;
use App\Training\Domain\Exercise\ExerciseNoteId;

final class ExerciseNoteIdType extends UuidType
{
    public const string NAME = 'exercise_note_id';

    protected function identifierClass(): string
    {
        return ExerciseNoteId::class;
    }
}
