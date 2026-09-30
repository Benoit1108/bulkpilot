<?php

declare(strict_types=1);

namespace App\Shared\Application\Notifying;

final readonly class DayTraining
{
    /**
     * @param list<RecordWorkout> $recordWorkouts finished since the time asked, with at least one record, the oldest first
     */
    public function __construct(
        /** The days of the active program; null without one. */
        public ?int $plannedPerWeek,
        /** Finished workouts started since the Monday of the user's week. */
        public int $sessionsThisWeek,
        /** A workout was started today (finished or in progress). */
        public bool $startedToday,
        public array $recordWorkouts,
    ) {
    }
}
