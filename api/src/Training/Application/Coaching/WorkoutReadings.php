<?php

declare(strict_types=1);

namespace App\Training\Application\Coaching;

use App\Shared\Application\Coaching\ExerciseStall;
use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\TrainingReadings;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Application\Program\ActiveProgram;
use App\Training\Domain\Progression\Stall;
use App\Training\Domain\Progression\StallDetector;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;

/**
 * The workouts as the Coach reads them (ADR-0028): what was done, what was planned, the records,
 * and the exercises that stall (spec §67).
 */
final readonly class WorkoutReadings implements TrainingReadings
{
    public function __construct(private WorkoutRepository $workouts, private ActiveProgram $program)
    {
    }

    public function between(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): TrainingReading
    {
        $start = $from->setTime(0, 0);
        $end = $to->setTime(0, 0)->modify('+1 day');
        // The oldest first, up to the end of the last day: records are judged against every session before.
        $history = array_reverse($this->workouts->historyOf($userId, WorkoutRepository::HISTORY_LIMIT, $end));
        $inRange = array_filter($history, static fn (Workout $workout): bool => $workout->startedAt() >= $start);

        $beaten = StallDetector::recordsByExercise($history);
        $byWorkout = StallDetector::recordsByWorkout($beaten);
        $records = array_sum(array_map(static fn (Workout $workout): int => $byWorkout[$workout->id()->value] ?? 0, $inRange));

        return new TrainingReading(
            \count($inRange),
            $this->program->plannedPerWeek($userId),
            $records,
            array_map(
                static fn (Stall $stall): ExerciseStall => new ExerciseStall($stall->exerciseName, $stall->sessions, $stall->since),
                StallDetector::stalls($history, $beaten, self::namesOf($history), $end),
            ),
        );
    }

    /**
     * @param list<Workout> $history
     *
     * @return array<string, string> the name of each exercise trained, by id (the latest name)
     */
    private static function namesOf(array $history): array
    {
        $names = [];
        foreach ($history as $workout) {
            foreach ($workout->exercises() as $exercise) {
                $names[$exercise->exerciseId->value] = $exercise->name;
            }
        }

        return $names;
    }
}
