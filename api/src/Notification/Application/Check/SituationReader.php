<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Notification\Domain\Check\GoalStep;
use App\Notification\Domain\Check\RecordSession;
use App\Notification\Domain\Check\Situation;
use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Notifying\CoachReports;
use App\Shared\Application\Notifying\EatingReadings;
use App\Shared\Application\Notifying\ReachedStep;
use App\Shared\Application\Notifying\RecordWorkout;
use App\Shared\Application\Notifying\TrainingActivity;
use App\Shared\Application\Notifying\WeighingReadings;

/**
 * Reads where the user stands from the other contexts (ADR-0029), in their time zone.
 */
final readonly class SituationReader
{
    /** Records of the workouts finished this long ago at most are news. */
    public const int RECORD_HOURS = 48;

    public function __construct(
        private EatingReadings $eating,
        private WeighingReadings $weighing,
        private TrainingActivity $training,
        private CoachReports $reports,
    ) {
    }

    public function at(AccountClock $account, \DateTimeImmutable $now): Situation
    {
        $local = $account->now($now);
        $day = new \DateTimeImmutable($local->format('Y-m-d'));
        $eating = $this->eating->day($account->userId, $day);
        $weighing = $this->weighing->day($account->userId, $day);
        $training = $this->training->on($account->userId, $local->setTime(0, 0), $now->modify(\sprintf('-%d hours', self::RECORD_HOURS)));

        return new Situation(
            $local,
            $eating->eatenKcal,
            $eating->targetKcal,
            $eating->lastLoggedAt,
            $weighing->weighedToday,
            $weighing->weighInsLastWeek,
            array_map(static fn (ReachedStep $step): GoalStep => new GoalStep($step->goalId, $step->weightKg, $step->target, $step->reachedOn), $weighing->reached),
            $training->plannedPerWeek,
            $training->sessionsThisWeek,
            $training->startedToday,
            array_map(static fn (RecordWorkout $workout): RecordSession => new RecordSession($workout->workoutId, $workout->records), $training->recordWorkouts),
            $this->reports->latestWeek($account->userId),
        );
    }
}
