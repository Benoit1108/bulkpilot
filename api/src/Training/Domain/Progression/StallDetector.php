<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Workout\Workout;

/**
 * Which exercises stall (spec §67): still trained lately, yet several sessions and a few weeks
 * since they last beat a record of any kind (load, reps, estimated max, volume…).
 */
final class StallDetector
{
    /** An exercise stalls after this many sessions without a record… */
    public const int SESSIONS = 4;
    /** …over at least this many days since its last one… */
    public const int DAYS = 21;
    /** …while still trained within these last days. */
    public const int RECENT_DAYS = 14;

    /**
     * @param list<Workout>      $history the oldest first, up to the end of the day judged
     * @param array<string, int> $beaten  records each workout beat, by workout id, those with the exercise only ({@see RecordBook::beatenInOrder})
     * @param \DateTimeImmutable $end     the end of the day judged
     */
    public static function stallOf(array $history, array $beaten, string $name, \DateTimeImmutable $end): ?Stall
    {
        $sessions = array_values(array_filter($history, static fn (Workout $workout): bool => isset($beaten[$workout->id()->value])));
        $last = end($sessions);
        if (false === $last || $last->startedAt() < $end->modify(\sprintf('-%d days', self::RECENT_DAYS))) {
            return null;
        }
        // The last session that beat a record, or the first one without any.
        $mark = 0;
        foreach ($sessions as $i => $workout) {
            if ($beaten[$workout->id()->value] > 0) {
                $mark = $i;
            }
        }
        $since = $sessions[$mark]->startedAt()->setTime(0, 0);
        $after = \count($sessions) - $mark - 1;
        if ($after < self::SESSIONS || $since > $end->modify(\sprintf('-%d days', self::DAYS + 1))) {
            return null;
        }

        return new Stall($name, $after, $since);
    }

    /**
     * The stalls of every exercise of the history, the most stalled first (then the oldest).
     *
     * @param list<Workout>                     $history the oldest first, up to the end of the day judged
     * @param array<string, array<string, int>> $beaten  {@see RecordBook::beatenInOrder} by exercise id
     * @param array<string, string>             $names   by exercise id
     *
     * @return list<Stall>
     */
    public static function stalls(array $history, array $beaten, array $names, \DateTimeImmutable $end): array
    {
        $stalls = [];
        foreach ($beaten as $exerciseId => $records) {
            $stall = self::stallOf($history, $records, $names[$exerciseId] ?? '', $end);
            if (null !== $stall) {
                $stalls[] = $stall;
            }
        }
        usort($stalls, static fn (Stall $a, Stall $b): int => [$b->sessions, $a->since] <=> [$a->sessions, $b->since]);

        return $stalls;
    }

    /**
     * @param list<Workout> $history
     *
     * @return array<string, array<string, int>> records beaten by workout id, by exercise id
     */
    public static function recordsByExercise(array $history): array
    {
        $ids = [];
        foreach ($history as $workout) {
            foreach ($workout->exercises() as $exercise) {
                $ids[$exercise->exerciseId->value] = $exercise->exerciseId;
            }
        }

        return array_map(static fn (ExerciseId $id): array => RecordBook::beatenInOrder($history, $id), $ids);
    }
}
