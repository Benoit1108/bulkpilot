<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

/**
 * The weight trend at the end of a day (spec §65: the trend, never one day's weight).
 */
final readonly class WeightFacts
{
    public function __construct(
        public ?float $average7Kg,
        /** Signed speed over the last 4 weeks; null when too few weigh-ins to tell. */
        public ?float $kgPerWeek,
        public int $weighIns7,
        public int $weighIns28,
    ) {
    }
}
