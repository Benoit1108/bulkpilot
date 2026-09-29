<?php

declare(strict_types=1);

namespace App\Training\Domain\Prescription;

use App\Training\Domain\Exception\InvalidPrescription;

/**
 * Reps in reserve: how many more reps were left in the tank (0 = to failure).
 */
final class Rir
{
    public const int MAX = 10;

    public static function valid(?int $rir): ?int
    {
        if (null !== $rir && ($rir < 0 || $rir > self::MAX)) {
            throw InvalidPrescription::because(\sprintf('reps in reserve go from 0 to %d', self::MAX));
        }

        return $rir;
    }
}
