<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutSet;

/**
 * An exercise in one workout, summed up for a progress chart.
 */
final readonly class SessionPoint
{
    public function __construct(
        public string $workoutId,
        public \DateTimeImmutable $date,
        public int $workingSets,
        public ?float $topLoadKg,
        public ?float $estimatedOneRepMax,
        public float $volumeKg,
        public ?int $bestReps,
        public ?int $bestSeconds,
    ) {
    }

    public static function of(Workout $workout, ExerciseId $exerciseId): ?self
    {
        $sets = $workout->workingSetsOf($exerciseId);
        if ([] === $sets) {
            return null;
        }
        $loads = array_filter(array_map(static fn (WorkoutSet $set): ?float => $set->loadKg, $sets), static fn (?float $load): bool => null !== $load && $load > 0);
        $estimates = array_filter(array_map(static fn (WorkoutSet $set): ?float => RecordBook::estimatedOneRepMax($set->loadKg ?? 0.0, $set->reps ?? 0), $sets), static fn (?float $estimate): bool => null !== $estimate);
        $reps = array_filter(array_map(static fn (WorkoutSet $set): ?int => $set->reps, $sets), static fn (?int $count): bool => null !== $count);
        $seconds = array_filter(array_map(static fn (WorkoutSet $set): ?int => $set->seconds, $sets), static fn (?int $count): bool => null !== $count);

        return new self(
            $workout->id()->value,
            $workout->startedAt(),
            \count($sets),
            [] === $loads ? null : max($loads),
            [] === $estimates ? null : max($estimates),
            WorkoutSet::totalVolume($sets),
            [] === $reps ? null : max($reps),
            [] === $seconds ? null : max($seconds),
        );
    }
}
