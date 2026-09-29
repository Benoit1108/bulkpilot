<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * Too few weigh-ins for a trend one can trust (spec §65).
 */
final readonly class WeighInRule implements Rule
{
    public const int MIN_PER_WEEK = 3;

    public function evaluate(Facts $facts): ?Finding
    {
        if ($facts->weight->weighIns7 >= self::MIN_PER_WEEK) {
            return null;
        }

        return new Finding(Kind::FewWeighIns, Level::Info, [
            'weighIns7' => $facts->weight->weighIns7,
            'weighIns28' => $facts->weight->weighIns28,
            'neededPerWeek' => self::MIN_PER_WEEK,
        ]);
    }
}
