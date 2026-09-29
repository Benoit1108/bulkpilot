<?php

declare(strict_types=1);

namespace App\Training\Application\Coaching;

use App\Shared\Application\Coaching\ExerciseStall;
use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Application\Coaching\TrainingReadings;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersionRepository;
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
    public function __construct(
        private WorkoutRepository $workouts,
        private ProgramRepository $programs,
        private ProgramVersionRepository $versions,
    ) {
    }

    public function between(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): TrainingReading
    {
        $start = $from->setTime(0, 0);
        $end = $to->setTime(0, 0)->modify('+1 day');
        // The oldest first, up to the end of the last day: records are judged against every session before.
        $history = array_reverse($this->workouts->historyOf($userId, WorkoutRepository::HISTORY_LIMIT, $end));
        $inRange = array_filter($history, static fn (Workout $workout): bool => $workout->startedAt() >= $start);

        $beaten = StallDetector::recordsByExercise($history);
        $records = 0;
        foreach ($beaten as $byWorkout) {
            foreach ($inRange as $workout) {
                $records += $byWorkout[$workout->id()->value] ?? 0;
            }
        }
        $program = $this->programs->activeOf($userId);

        return new TrainingReading(
            \count($inRange),
            null === $program ? null : \count($this->versions->currentOf($program)->days()),
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
