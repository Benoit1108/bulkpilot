<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Goal\GoalProgress;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\TrendPoint;

/**
 * The weight page (spec §51–53, §55): the latest weigh-in, the averages and the speed ending
 * today, the change since the first weigh-in, the goal, and the chart of the period.
 */
final readonly class WeightSummaryView
{
    /**
     * @param list<TrendPoint>  $points
     * @param list<WeekAverage> $weeks
     */
    public function __construct(
        public ?DayWeight $latest,
        public ?DayWeight $first,
        public ?float $average7,
        public ?float $average30,
        public ?float $kgPerWeek,
        public ?TrendDirection $direction,
        public ?float $sinceStartKg,
        /** The last weigh-in of the period minus its first; null with fewer than two. */
        public ?float $periodChangeKg,
        public ?GoalProgress $goal,
        public array $points,
        public array $weeks,
    ) {
    }
}
