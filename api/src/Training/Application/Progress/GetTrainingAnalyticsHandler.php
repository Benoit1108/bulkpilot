<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Calendar\Days;
use App\Training\Domain\Progression\StallDetector;
use App\Training\Domain\Workout\WorkoutRepository;
use App\Training\Domain\Workout\WorkoutSet;

final readonly class GetTrainingAnalyticsHandler implements QueryHandler
{
    /** "Everything" starts here: long before any workout. */
    private const string EARLIEST = '2000-01-01';

    public function __construct(private WorkoutRepository $workouts)
    {
    }

    public function __invoke(GetTrainingAnalytics $query): TrainingAnalyticsView
    {
        $today = $query->today->setTime(0, 0);
        $from = $query->period->firstDay($today);
        // Every session of the period, however many (the oldest first): the totals are exact.
        $inPeriod = $this->workouts->startedSince($query->userId, $from ?? new \DateTimeImmutable(self::EARLIEST));
        // Each session's records are judged against every one before it: the last sessions when the
        // period holds fewer (a recent period lies within them), the period itself otherwise ("all"
        // is then exact; a long period judges its first sessions against the period only).
        $history = \count($inPeriod) >= WorkoutRepository::HISTORY_LIMIT
            ? $inPeriod
            : array_reverse($this->workouts->historyOf($query->userId, WorkoutRepository::HISTORY_LIMIT));
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
