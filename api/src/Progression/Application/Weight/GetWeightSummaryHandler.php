<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\TrendPoint;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\WeightTrend;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Calendar\Days;

final readonly class GetWeightSummaryHandler implements QueryHandler
{
    public function __construct(private WeightReadings $readings, private WeighInRepository $weighIns, private WeightGoalRepository $goals)
    {
    }

    public function __invoke(GetWeightSummary $query): WeightSummaryView
    {
        $today = $query->today->setTime(0, 0);
        $periodStart = $query->period->firstDay($today);
        $goal = $this->goals->current($query->userId);
        $weighIns = $this->readings->since($query->userId, WeightReadings::earliest($periodStart, $today, $goal));

        $latest = $weighIns[\count($weighIns) - 1] ?? null;
        $first = $this->weighIns->first($query->userId);
        $firstDay = null === $first ? null : DayWeight::of($first);
        $kgPerWeek = WeightTrend::kgPerWeek($weighIns, $today);
        $points = WeightTrend::series($weighIns, $periodStart);

        return new WeightSummaryView(
            $latest,
            $firstDay,
            WeightTrend::average($weighIns, $today, 7),
            WeightTrend::average($weighIns, $today, 30),
            $kgPerWeek,
            WeightTrend::direction($kgPerWeek),
            null === $latest || null === $firstDay ? null : round($latest->kg - $firstDay->kg, 1),
            \count($points) < 2 ? null : round($points[\count($points) - 1]->kg - $points[0]->kg, 1),
            WeightReadings::progress($goal, $weighIns, $today),
            $points,
            self::weeks($points),
        );
    }

    /**
     * @param list<TrendPoint> $points
     *
     * @return list<WeekAverage>
     */
    private static function weeks(array $points): array
    {
        $weeks = [];
        foreach ($points as $point) {
            $weeks[Days::weekOf($point->day)->format('Y-m-d')][] = $point->kg;
        }

        return array_map(
            static fn (string $week, array $kgs): WeekAverage => new WeekAverage(new \DateTimeImmutable($week), round(array_sum($kgs) / \count($kgs), 2), \count($kgs)),
            array_keys($weeks),
            array_values($weeks),
        );
    }
}
