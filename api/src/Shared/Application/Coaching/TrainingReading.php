<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

final readonly class TrainingReading
{
    /**
     * @param list<ExerciseStall> $stalls the most stalled first
     */
    public function __construct(
        public int $sessions,
        /** The days of the active program: the sessions planned a week; null without a program. */
        public ?int $plannedPerWeek,
        /** Personal records beaten in those sessions. */
        public int $records,
        public array $stalls,
    ) {
    }
}
