<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Calendar\Days;
use App\Training\Domain\Progression\StallDetector;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;
use App\Training\Domain\Workout\WorkoutSet;

final readonly class GetTrainingAnalyticsHandler implements QueryHandler
{
    public function __construct(private WorkoutRepository $workouts)
    {
    }

    public function __invoke(GetTrainingAnalytics $query): TrainingAnalyticsView
    {
        $today = $query->today->setTime(0, 0);
        $from = $query->period->firstDay($today);
        // The last sessions (a few years of training), the oldest first: each session's records
        // are judged against every one before it, the period only picks which ones count.
        $history = array_reverse($this->workouts->historyOf($query->userId, WorkoutRepository::HISTORY_LIMIT));
        $inPeriod = array_values(array_filter($history, static fn (Workout $workout): bool => null === $from || $workout->startedAt() >= $from));
        $records = StallDetector::recordsByWorkout(StallDetector::recordsByExercise($history));

        $first = $from ?? ($inPeriod[0] ?? null)?->startedAt()->setTime(0, 0);
        $weeks = [];
        if (null !== $first) {
            for ($week = Days::weekOf($first); $week <= $today; $week = $week->modify('+7 days')) {
                $weeks[$week->format('Y-m-d')] = new TrainingTotals();
            }
        }
        $totals = new TrainingTotals();
        foreach ($inPeriod as $workout) {
            $working = array_values(array_filter($workout->sets(), static fn (WorkoutSet $set): bool => $set->kind->isWorking()));
            $beaten = $records[$workout->id()->value] ?? 0;
            $totals = $totals->withSession($working, $beaten);
            $week = Days::weekOf($workout->startedAt())->format('Y-m-d');
            $weeks[$week] = ($weeks[$week] ?? new TrainingTotals())->withSession($working, $beaten);
        }
        // Sessions over the days of the period (from its first day, or the first session), in weeks.
        $span = null === $first ? null : (Days::between($first, $today) + 1) / 7;

        return new TrainingAnalyticsView($totals, $weeks, null === $span ? null : round($totals->sessions / $span, 1));
    }
}
