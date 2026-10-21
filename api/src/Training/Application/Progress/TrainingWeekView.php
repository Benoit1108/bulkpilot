<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

final readonly class TrainingWeekView
{
    /**
     * @param array<string, int> $muscles working sets of the week under way by main muscle, the most first
     */
    public function __construct(
        public \DateTimeImmutable $week,
        public array $muscles,
        public \DateTimeImmutable $lastWeek,
        public TrainingTotals $lastWeekTotals,
    ) {
    }
}
