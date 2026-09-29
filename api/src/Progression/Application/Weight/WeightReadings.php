<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Goal\GoalProgress;
use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\WeightTrend;
use App\Shared\Domain\Identifier\UserId;

/**
 * Loads the weigh-ins a reading needs in one query, and reads the goal against them.
 */
final readonly class WeightReadings
{
    public function __construct(private WeighInRepository $weighIns)
    {
    }

    /**
     * @return list<DayWeight> every weigh-in from that day on (all when null), the oldest first
     */
    public function since(UserId $userId, ?\DateTimeImmutable $day): array
    {
        return array_map(DayWeight::of(...), $this->weighIns->since($userId, $day));
    }

    /**
     * @param list<DayWeight> $weighIns covering the goal's start and the last four weeks
     */
    public static function progress(?WeightGoal $goal, array $weighIns, \DateTimeImmutable $today): ?GoalProgress
    {
        $latest = $weighIns[\count($weighIns) - 1] ?? null;
        if (null === $goal || null === $latest) {
            return null;
        }

        return GoalProgress::of($goal, $weighIns, $latest, WeightTrend::kgPerWeek($weighIns, $today));
    }

    /** What a goal's progress reads: from its start, and the four weeks of the speed. */
    public static function forGoal(WeightGoal $goal, \DateTimeImmutable $today): \DateTimeImmutable
    {
        return min($goal->startDay(), $today->modify(\sprintf('-%d days', WeightTrend::SPEED_DAYS)));
    }

    /**
     * The earliest day a reading needs: the month before the period (its 30-day averages), the four
     * weeks of the speed, the goal's start. Null for everything.
     */
    public static function earliest(?\DateTimeImmutable $periodStart, \DateTimeImmutable $today, ?WeightGoal $goal): ?\DateTimeImmutable
    {
        if (null === $periodStart) {
            return null;
        }
        $days = [$periodStart->modify('-29 days'), $today->modify(\sprintf('-%d days', WeightTrend::SPEED_DAYS))];

        return min(null === $goal ? $days : [...$days, self::forGoal($goal, $today)]);
    }
}
