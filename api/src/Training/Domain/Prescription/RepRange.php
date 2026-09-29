<?php

declare(strict_types=1);

namespace App\Training\Domain\Prescription;

use App\Training\Domain\Exception\InvalidPrescription;

/**
 * The target of a set: 8 to 12 reps (or 30 to 45 s for a timed exercise). A single value is a
 * range of one: 5 reps.
 */
final readonly class RepRange
{
    public const int MAX = 300;

    public function __construct(public int $min, public int $max)
    {
        if ($min < 1 || $max > self::MAX || $min > $max) {
            throw InvalidPrescription::because(\sprintf('a target range goes from 1 to %d, lowest first', self::MAX));
        }
    }

    public function equals(self $other): bool
    {
        return $this->min === $other->min && $this->max === $other->max;
    }
}
