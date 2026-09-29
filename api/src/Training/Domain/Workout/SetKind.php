<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

/**
 * Spec §34. Supersets are blocks of exercises, not a kind of set.
 */
enum SetKind: string
{
    case Warmup = 'warmup';
    /** A heavier ramp-up set before the working ones. */
    case Approach = 'approach';
    case Normal = 'normal';
    /** A hard working set, taken close to failure. */
    case Effective = 'effective';
    /** Lighter, right after the previous set, without rest. */
    case Drop = 'drop';

    /** What counts for progression and records. */
    public function isWorking(): bool
    {
        return self::Normal === $this || self::Effective === $this;
    }
}
