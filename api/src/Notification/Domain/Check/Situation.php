<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

/**
 * Where the user stands at a moment, in their own time zone: what the rules look at.
 */
final readonly class Situation
{
    /**
     * @param list<GoalStep>      $reachedSteps
     * @param list<RecordSession> $recordSessions workouts with records, finished lately
     */
    public function __construct(
        /** The user's local time. */
        public \DateTimeImmutable $now,
        public float $eatenKcal,
        public ?int $targetKcal,
        public ?\DateTimeImmutable $lastLoggedAt,
        public bool $weighedToday,
        public int $weighInsLastWeek,
        public array $reachedSteps,
        public ?int $plannedPerWeek,
        public int $sessionsThisWeek,
        public bool $startedToday,
        public array $recordSessions,
        /** The Monday of the latest weekly report of the Coach. */
        public ?\DateTimeImmutable $latestReportWeek,
    ) {
    }

    public function today(): string
    {
        return $this->now->format('Y-m-d');
    }

    /** Local "HH:MM" from which a rule looks. */
    public function isFrom(string $time): bool
    {
        return $this->now->format('H:i') >= $time;
    }
}
