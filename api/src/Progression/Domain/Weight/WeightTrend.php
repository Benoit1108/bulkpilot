<?php

declare(strict_types=1);

namespace App\Progression\Domain\Weight;

use App\Shared\Domain\Calendar\Days;

/**
 * What a series of weigh-ins says (spec §51): averages over the last days, and the speed of the
 * change. A day's weight moves with water and meals; the averages and the speed are what to read.
 * Every calculation takes the weigh-ins the oldest first.
 */
final class WeightTrend
{
    /** The speed reads the last four weeks, from at least four weigh-ins over a week or more. */
    public const int SPEED_DAYS = 28;
    public const int SPEED_MIN_WEIGH_INS = 4;
    public const int SPEED_MIN_SPAN_DAYS = 7;
    /** Under 50 g a week either way, the weight is stable. */
    public const float STABLE_KG_PER_WEEK = 0.05;

    /**
     * Mean of the weigh-ins of the `$days` days ending `$end` (included); null without any.
     *
     * @param list<DayWeight> $weighIns
     */
    public static function average(array $weighIns, \DateTimeImmutable $end, int $days): ?float
    {
        $window = self::within($weighIns, $end, $days);

        return [] === $window ? null : round(array_sum(array_map(static fn (DayWeight $weighIn): float => $weighIn->kg, $window)) / \count($window), 2);
    }

    /**
     * Kilograms a week over the last four weeks ending `$end` (least squares, so one odd day moves
     * it little); null with too few weigh-ins to say.
     *
     * @param list<DayWeight> $weighIns
     */
    public static function kgPerWeek(array $weighIns, \DateTimeImmutable $end): ?float
    {
        $window = self::within($weighIns, $end, self::SPEED_DAYS);
        if (\count($window) < self::SPEED_MIN_WEIGH_INS) {
            return null;
        }
        $first = $window[0]->day;
        $xs = array_map(static fn (DayWeight $weighIn): int => Days::between($first, $weighIn->day), $window);
        if (max($xs) < self::SPEED_MIN_SPAN_DAYS) {
            return null;
        }
        $ys = array_map(static fn (DayWeight $weighIn): float => $weighIn->kg, $window);
        $meanX = array_sum($xs) / \count($xs);
        $meanY = array_sum($ys) / \count($ys);
        $covariance = 0.0;
        $variance = 0.0;
        foreach ($xs as $i => $x) {
            $covariance += ($x - $meanX) * ($ys[$i] - $meanY);
            $variance += ($x - $meanX) ** 2;
        }

        return round($covariance / $variance * 7, 2);
    }

    public static function direction(?float $kgPerWeek): ?TrendDirection
    {
        return match (true) {
            null === $kgPerWeek => null,
            $kgPerWeek > self::STABLE_KG_PER_WEEK => TrendDirection::Gaining,
            $kgPerWeek < -self::STABLE_KG_PER_WEEK => TrendDirection::Losing,
            default => TrendDirection::Stable,
        };
    }

    /**
     * The chart: each weigh-in from `$from` (everything when null) with the averages ending that day,
     * which also read the weigh-ins before `$from`.
     *
     * @param list<DayWeight> $weighIns
     *
     * @return list<TrendPoint>
     */
    public static function series(array $weighIns, ?\DateTimeImmutable $from): array
    {
        $average7 = self::rollingMeans($weighIns, 7);
        $average30 = self::rollingMeans($weighIns, 30);
        $points = [];
        foreach ($weighIns as $i => $weighIn) {
            if (null === $from || $weighIn->day >= $from) {
                $points[] = new TrendPoint($weighIn->day, $weighIn->kg, $average7[$i], $average30[$i]);
            }
        }

        return $points;
    }

    /**
     * For each weigh-in, the mean of those of the `$days` days ending its day: one pass with a
     * sliding window (years of daily weigh-ins stay cheap).
     *
     * @param list<DayWeight> $weighIns the oldest first
     *
     * @return list<float>
     */
    private static function rollingMeans(array $weighIns, int $days): array
    {
        $first = $weighIns[0]->day ?? null;
        $at = array_map(static fn (DayWeight $weighIn): int => null === $first ? 0 : Days::between($first, $weighIn->day), $weighIns);
        $means = [];
        $start = 0;
        $sum = 0.0;
        foreach ($weighIns as $i => $weighIn) {
            $sum += $weighIn->kg;
            while ($at[$i] - $at[$start] >= $days) {
                $sum -= $weighIns[$start]->kg;
                ++$start;
            }
            $means[] = round($sum / ($i - $start + 1), 2);
        }

        return $means;
    }

    /**
     * @param list<DayWeight> $weighIns
     *
     * @return list<DayWeight>
     */
    private static function within(array $weighIns, \DateTimeImmutable $end, int $days): array
    {
        return array_values(array_filter($weighIns, static function (DayWeight $weighIn) use ($end, $days): bool {
            $age = Days::between($weighIn->day, $end);

            return $age >= 0 && $age < $days;
        }));
    }
}
