<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

/**
 * A week's mean weight (Monday to Sunday): what the calories are compared with (spec §56).
 */
final readonly class WeekAverage
{
    public function __construct(public \DateTimeImmutable $week, public float $averageKg, public int $weighIns)
    {
    }
}
