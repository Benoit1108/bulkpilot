<?php

declare(strict_types=1);

namespace App\Training\Application\Notifying;

use App\Shared\Application\Notifying\DayTraining;
use App\Shared\Application\Notifying\RecordWorkout;
use App\Shared\Application\Notifying\TrainingActivity;
use App\Shared\Domain\Calendar\Days;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Application\Program\ActiveProgram;
use App\Training\Domain\Progression\StallDetector;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;

/**
 * The training as the notifications read it (ADR-0029): the week so far against the program,
 * today's session, and the records of the latest workouts. Run every quarter of an hour: it reads
 * the week's workouts, and the history only when a workout was finished lately.
 */
final readonly class WorkoutActivity implements TrainingActivity
{
    public function __construct(private WorkoutRepository $workouts, private ActiveProgram $program)
    {
    }

    public function on(UserId $userId, \DateTimeImmutable $dayStart, \DateTimeImmutable $since): DayTraining
    {
        $week = $this->workouts->startedSince($userId, Days::weekOf($dayStart));
        $inProgress = $this->workouts->inProgressOf($userId);

        return new DayTraining(
            $this->program->plannedPerWeek($userId),
            \count($week),
            array_any($week, static fn (Workout $workout): bool => $workout->startedAt() >= $dayStart) || (null !== $inProgress && $inProgress->startedAt() >= $dayStart),
            $this->recordWorkouts($userId, $since),
        );
    }

    /**
     * @return list<RecordWorkout> the workouts finished since that time that beat records, the oldest first
     */
    private function recordWorkouts(UserId $userId, \DateTimeImmutable $since): array
    {
        $recent = $this->workouts->finishedSince($userId, $since);
        if ([] === $recent) {
            return [];
        }
        $exerciseIds = [];
        foreach ($recent as $workout) {
            foreach ($workout->exercises() as $exercise) {
                $exerciseIds[$exercise->exerciseId->value] = $exercise->exerciseId;
            }
        }
        // Their records are judged against every earlier workout with the same exercises.
        $records = StallDetector::recordsByWorkout(StallDetector::recordsByExercise($this->workouts->historyWith($userId, array_values($exerciseIds))));

        $withRecords = [];
        foreach ($recent as $workout) {
            $beaten = $records[$workout->id()->value] ?? 0;
            if ($beaten > 0) {
                $withRecords[] = new RecordWorkout($workout->id()->value, $beaten);
            }
        }

        return $withRecords;
    }
}
