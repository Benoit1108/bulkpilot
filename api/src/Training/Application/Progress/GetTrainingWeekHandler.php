<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Calendar\Days;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Progression\MuscleVolume;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;
use App\Training\Domain\Workout\WorkoutSet;

final readonly class GetTrainingWeekHandler implements QueryHandler
{
    public function __construct(private WorkoutRepository $workouts, private ExerciseRepository $exercises)
    {
    }

    public function __invoke(GetTrainingWeek $query): TrainingWeekView
    {
        $week = Days::weekOf($query->today);
        $lastWeek = $week->modify('-7 days');
        $thisWeek = [];
        $lastTotals = new TrainingTotals();
        foreach ($this->workouts->startedSince($query->userId, $lastWeek) as $workout) {
            if ($workout->startedAt() >= $week) {
                $thisWeek[] = $workout;
                continue;
            }
            // Records are the period figures' business: the last week says what was lifted.
            $lastTotals = $lastTotals->withSession(self::workingSetsOf($workout), 0);
        }
        $exercises = $this->exercises->usableAmong($query->userId, MuscleVolume::exercisesOf($thisWeek));

        return new TrainingWeekView($week, MuscleVolume::workingSetsByMuscle($thisWeek, $exercises), $lastWeek, $lastTotals);
    }

    /**
     * @return list<WorkoutSet>
     */
    private static function workingSetsOf(Workout $workout): array
    {
        return array_values(array_filter($workout->sets(), static fn (WorkoutSet $set): bool => $set->kind->isWorking()));
    }
}
