<?php

declare(strict_types=1);

namespace App\Training\Domain\Exercise;

/**
 * What a set records: a load and reps, reps with the body's weight (plus an optional added
 * load), or a duration (a plank).
 */
enum Measurement: string
{
    case Weight = 'weight';
    case Bodyweight = 'bodyweight';
    case Duration = 'duration';

    public function countsReps(): bool
    {
        return self::Duration !== $this;
    }
}
