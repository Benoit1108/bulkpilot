<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutSet;

/**
 * The personal records of an exercise over workouts, and those a workout beat (spec §47).
 * Only working sets count; a record beats an earlier workout (the first time is not one, nor
 * is a tie).
 */
final class RecordBook
{
    /** Beyond this, the Epley estimate means little. */
    public const int MAX_REPS_FOR_ESTIMATE = 12;

    /**
     * @param list<Workout> $workouts
     *
     * @return array<string, PersonalRecord> the best of each kind, by kind (reps at a load are left out)
     */
    public static function bests(array $workouts, ExerciseId $exerciseId): array
    {
        $bests = [];
        foreach (self::allBests($workouts, $exerciseId) as $record) {
            if (RecordKind::RepsAtLoad !== $record->kind) {
                $bests[$record->kind->value] = $record;
            }
        }

        return $bests;
    }

    /**
     * @param list<Workout> $before finished before this one started
     *
     * @return list<PersonalRecord> what this workout beat
     */
    public static function beatenBy(Workout $workout, array $before, ExerciseId $exerciseId): array
    {
        $previous = self::allBests($before, $exerciseId);

        return array_values(array_filter(
            self::performances($workout, $exerciseId),
            static fn (PersonalRecord $record): bool => isset($previous[$record->key()]) && $record->value > $previous[$record->key()]->value,
        ));
    }

    /**
     * The records each workout beat, in one pass over them (the same rule as `beatenBy`: better than
     * every earlier one; the first time is no record).
     *
     * @param list<Workout> $workouts the oldest first
     *
     * @return array<string, int> by workout id, those with the exercise only
     */
    public static function beatenInOrder(array $workouts, ExerciseId $exerciseId): array
    {
        $bests = [];
        $beaten = [];
        foreach ($workouts as $workout) {
            $performances = self::performances($workout, $exerciseId);
            if ([] === $performances) {
                continue;
            }
            $count = 0;
            foreach ($performances as $record) {
                $best = $bests[$record->key()] ?? null;
                if (null !== $best && $record->value > $best->value) {
                    ++$count;
                }
                if (null === $best || $record->value > $best->value) {
                    $bests[$record->key()] = $record;
                }
            }
            $beaten[$workout->id()->value] = $count;
        }

        return $beaten;
    }

    public static function estimatedOneRepMax(float $loadKg, int $reps): ?float
    {
        if ($reps < 1 || $reps > self::MAX_REPS_FOR_ESTIMATE || $loadKg <= 0) {
            return null;
        }

        return round(1 === $reps ? $loadKg : $loadKg * (1 + $reps / 30), 1);
    }

    /**
     * @param list<Workout> $workouts
     *
     * @return array<string, PersonalRecord> the best of every kind (and every load), by record key
     */
    private static function allBests(array $workouts, ExerciseId $exerciseId): array
    {
        $bests = [];
        foreach ($workouts as $workout) {
            foreach (self::performances($workout, $exerciseId) as $record) {
                $best = $bests[$record->key()] ?? null;
                if (null === $best || $record->value > $best->value) {
                    $bests[$record->key()] = $record;
                }
            }
        }

        return $bests;
    }

    /**
     * The best of each kind within one workout.
     *
     * @return list<PersonalRecord>
     */
    private static function performances(Workout $workout, ExerciseId $exerciseId): array
    {
        $sets = $workout->workingSetsOf($exerciseId);
        $best = [];
        $keep = static function (RecordKind $kind, float $value, ?float $load = null) use (&$best, $workout): void {
            $record = new PersonalRecord($kind, $value, $workout->startedAt(), $workout->id(), $load);
            if ($value > 0 && (!isset($best[$record->key()]) || $value > $best[$record->key()]->value)) {
                $best[$record->key()] = $record;
            }
        };

        foreach ($sets as $set) {
            if (null !== $set->seconds) {
                $keep(RecordKind::Duration, $set->seconds);
                continue;
            }
            $reps = $set->reps ?? 0;
            $load = $set->loadKg ?? 0.0;
            if ($load > 0 && $reps > 0) {
                $keep(RecordKind::Load, $load);
                $keep(RecordKind::RepsAtLoad, $reps, $load);
                $keep(RecordKind::EstimatedOneRepMax, self::estimatedOneRepMax($load, $reps) ?? 0.0);
            } else {
                $keep(RecordKind::Reps, $reps);
            }
        }
        $keep(RecordKind::Volume, WorkoutSet::totalVolume($sets));

        return array_values($best);
    }
}
