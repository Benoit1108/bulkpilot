<?php

declare(strict_types=1);

namespace App\Progression\Application\Notifying;

use App\Progression\Application\Weight\WeightReadings;
use App\Progression\Domain\Goal\StepProgress;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Progression\Domain\Weight\DayWeight;
use App\Shared\Application\Notifying\DayWeighing;
use App\Shared\Application\Notifying\ReachedStep;
use App\Shared\Application\Notifying\WeighingReadings;
use App\Shared\Domain\Identifier\UserId;

/**
 * A day of weigh-ins and the goal followed, as the notifications read them (ADR-0029).
 */
final readonly class WeighInDay implements WeighingReadings
{
    public function __construct(private WeightReadings $readings, private WeightGoalRepository $goals)
    {
    }

    public function day(UserId $userId, \DateTimeImmutable $day): DayWeighing
    {
        $today = $day->setTime(0, 0);
        $goal = $this->goals->current($userId);
        $from = null === $goal ? $today->modify('-7 days') : min(WeightReadings::forGoal($goal, $today), $today->modify('-7 days'));
        $weighIns = array_values(array_filter($this->readings->since($userId, $from), static fn (DayWeight $weighIn): bool => $weighIn->day <= $today));
        $weekStart = $today->modify('-7 days');
        $progress = WeightReadings::progress($goal, $weighIns, $today);

        return new DayWeighing(
            [] !== array_filter($weighIns, static fn (DayWeight $weighIn): bool => $weighIn->day->format('Y-m-d') === $today->format('Y-m-d')),
            \count(array_filter($weighIns, static fn (DayWeight $weighIn): bool => $weighIn->day >= $weekStart && $weighIn->day < $today)),
            null === $progress ? [] : array_values(array_map(
                static fn (StepProgress $step): ReachedStep => new ReachedStep($progress->goal->id()->value, $step->kg, $step->target, $step->reachedOn ?? $today),
                array_filter($progress->steps, static fn (StepProgress $step): bool => null !== $step->reachedOn),
            )),
        );
    }
}
