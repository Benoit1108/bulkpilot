<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutSet;

final readonly class WorkoutSummaryView
{
    public function __construct(
        public string $id,
        public ?string $dayName,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public int $exercises,
        public int $workingSets,
        /** Load × reps of the working sets, in kg. */
        public float $volumeKg,
    ) {
    }

    public static function of(Workout $workout): self
    {
        $working = array_filter($workout->sets(), static fn (WorkoutSet $set): bool => $set->kind->isWorking());
        $done = array_unique(array_map(static fn (WorkoutSet $set): int => $set->exerciseIndex, $workout->sets()));

        return new self(
            $workout->id()->value,
            $workout->programDay()?->dayName,
            $workout->startedAt(),
            $workout->finishedAt(),
            \count($done),
            \count($working),
            WorkoutSet::totalVolume(array_values($working)),
        );
    }
}
