<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

final readonly class WeightReading
{
    public function __construct(
        /** The mean of the last 7 days' weigh-ins, null without one. */
        public ?float $average7Kg,
        /** Signed speed over the last 4 weeks, null when too few weigh-ins to tell. */
        public ?float $kgPerWeek,
        /** How many days had a weigh-in, of the last 7 and of the last 28. */
        public int $weighIns7,
        public int $weighIns28,
    ) {
    }
}
