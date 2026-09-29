<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

final readonly class TrainingAnalyticsView
{
    /**
     * @param array<string, TrainingTotals> $weeks by Monday (Y-m-d), the oldest first, weeks without a session included
     */
    public function __construct(public TrainingTotals $totals, public array $weeks, public ?float $sessionsPerWeek)
    {
    }
}
