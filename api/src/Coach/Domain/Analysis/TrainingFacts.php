<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

final readonly class TrainingFacts
{
    /**
     * @param list<Stall> $stalls the most stalled first
     */
    public function __construct(public int $sessions, public ?int $plannedPerWeek, public int $records, public array $stalls)
    {
    }
}
