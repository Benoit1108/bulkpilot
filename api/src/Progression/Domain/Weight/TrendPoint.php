<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

/**
 * A point of the weight chart: the day's weight and the averages ending that day.
 */
final readonly class TrendPoint
{
    public function __construct(
        public \DateTimeImmutable $day,
        public float $kg,
        public float $average7,
        public float $average30,
    ) {
    }
}
