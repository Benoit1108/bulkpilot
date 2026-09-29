<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Workout\WorkoutSet;

/**
 * Suggests the next target of an exercise from its working sets last time, following the
 * exercise's strategy (spec §37). Deterministic; nothing is applied without the user.
 *
 * Only the sets at the top load count, and only as many as the program plans: a lighter set, or
 * an extra one, does not change what the planned sets achieved.
 */
final class ProgressionAdvisor
{
    /** RIR assumed when the program sets none, for the strategies that read effort. */
    public const int DEFAULT_TARGET_RIR = 2;
    /** Seconds added to a timed exercise, as "one more rep". */
    public const int SECONDS_STEP = 5;

    /**
     * @param list<WorkoutSet> $last every set of the exercise last time (warm-ups are ignored)
     */
    public static function suggest(PlannedExercise $planned, Measurement $measurement, array $last): ?Suggestion
    {
        $working = array_values(array_filter($last, static fn (WorkoutSet $set): bool => $set->kind->isWorking()));
        if ([] === $working) {
            return null;
        }
        if (Measurement::Duration === $measurement) {
            return self::timed($planned, $working);
        }

        $load = max(array_map(static fn (WorkoutSet $set): float => $set->loadKg ?? 0.0, $working));
        $atLoad = \array_slice(array_values(array_filter($working, static fn (WorkoutSet $set): bool => ($set->loadKg ?? 0.0) === $load)), 0, $planned->sets);
        $reps = array_map(static fn (WorkoutSet $set): int => $set->reps ?? 0, $atLoad);
        $fewest = min([...$reps, $planned->repRange->max]);
        $most = max([...$reps, 0]);
        $complete = \count($atLoad) >= $planned->sets;
        $range = $planned->repRange;

        $up = Suggestion::load(SuggestionReason::IncreaseLoad, $load + $planned->incrementKg, $range->min);
        $same = Suggestion::load(SuggestionReason::Repeat, $load, $most);
        $oneMore = $fewest < $range->max ? Suggestion::load(SuggestionReason::AddReps, $load, $fewest + 1) : $same;

        return match ($planned->strategy) {
            ProgressionStrategy::DoubleProgression => $complete && $fewest >= $range->max ? $up : $oneMore,
            ProgressionStrategy::Linear => $complete && $fewest >= $range->min ? $up : $same,
            ProgressionStrategy::FixedLoad => $oneMore,
            ProgressionStrategy::FixedReps => $complete && $fewest >= $range->max && self::easierThanPlanned($planned, $atLoad)
                ? Suggestion::load(SuggestionReason::IncreaseLoad, $load + $planned->incrementKg, $range->max)
                : Suggestion::load(SuggestionReason::Repeat, $load, $range->max),
            ProgressionStrategy::TargetRir => self::byEffort($planned, $load, $most, $atLoad),
        };
    }

    /**
     * @param non-empty-list<WorkoutSet> $working
     */
    private static function timed(PlannedExercise $planned, array $working): Suggestion
    {
        $best = max(array_map(static fn (WorkoutSet $set): int => $set->seconds ?? 0, $working));
        if (ProgressionStrategy::FixedReps === $planned->strategy || $best >= $planned->repRange->max) {
            return Suggestion::duration(SuggestionReason::Repeat, $best);
        }

        return Suggestion::duration(SuggestionReason::AddReps, min($planned->repRange->max, $best + self::SECONDS_STEP));
    }

    /**
     * @param list<WorkoutSet> $sets
     */
    private static function byEffort(PlannedExercise $planned, float $load, int $reps, array $sets): Suggestion
    {
        $rirs = self::rirsOf($sets);
        if ([] === $rirs) {
            return Suggestion::load(SuggestionReason::Repeat, $load, $reps);
        }
        $gap = array_sum($rirs) / \count($rirs) - ($planned->targetRir ?? self::DEFAULT_TARGET_RIR);

        return match (true) {
            $gap >= 2 => Suggestion::load(SuggestionReason::IncreaseLoad, $load + $planned->incrementKg, $reps),
            $gap <= -2 && $load > 0 => Suggestion::load(SuggestionReason::DecreaseLoad, max(0.0, $load - $planned->incrementKg), $reps),
            default => Suggestion::load(SuggestionReason::Repeat, $load, $reps),
        };
    }

    /**
     * Without RIR noted, reaching the reps is enough.
     *
     * @param list<WorkoutSet> $sets
     */
    private static function easierThanPlanned(PlannedExercise $planned, array $sets): bool
    {
        $rirs = self::rirsOf($sets);

        return [] === $rirs || min($rirs) > ($planned->targetRir ?? self::DEFAULT_TARGET_RIR);
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return list<int> the RIR noted, where it was
     */
    private static function rirsOf(array $sets): array
    {
        return array_values(array_filter(array_map(static fn (WorkoutSet $set): ?int => $set->rir, $sets), static fn (?int $rir): bool => null !== $rir));
    }
}
