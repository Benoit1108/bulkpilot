<?php

declare(strict_types=1);

namespace App\Training\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class ExerciseNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Exercise "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'training.exercise_not_found';
    }
}
