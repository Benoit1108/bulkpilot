<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

/**
 * The daily target in force, from which day, and the weight change it was estimated for when it
 * came from an estimate (signed kg per week: positive to gain).
 */
final readonly class TargetReading
{
    public function __construct(
        public \DateTimeImmutable $effectiveFrom,
        public int $energyKcal,
        public int $proteinGrams,
        public ?float $weeklyChangeKg,
    ) {
    }
}
