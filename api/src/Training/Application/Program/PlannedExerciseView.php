<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Program\PlannedExercise;

final readonly class PlannedExerciseView
{
    /**
     * @param bool $available false once the user deleted this exercise of theirs
     */
    public function __construct(public PlannedExercise $planned, public string $name, public Measurement $measurement, public bool $available)
    {
    }

    public static function of(PlannedExercise $planned, Exercise $exercise): self
    {
        return new self($planned, $exercise->name(), $exercise->measurement(), !$exercise->isDeleted());
    }
}
