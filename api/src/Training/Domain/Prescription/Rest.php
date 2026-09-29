<?php

declare(strict_types=1);

namespace App\Training\Domain\Prescription;

use App\Training\Domain\Exception\InvalidPrescription;

final class Rest
{
    public const int MAX_SECONDS = 900;

    public static function valid(int $seconds): int
    {
        if ($seconds < 0 || $seconds > self::MAX_SECONDS) {
            throw InvalidPrescription::because(\sprintf('a rest lasts 0 to %d seconds', self::MAX_SECONDS));
        }

        return $seconds;
    }
}
