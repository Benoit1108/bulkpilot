<?php

declare(strict_types=1);

namespace App\Training\Application\Library;

use App\Training\Domain\Exercise\ExerciseSpec;

final readonly class LibraryExercise
{
    public function __construct(public string $key, public ExerciseSpec $spec)
    {
    }
}
