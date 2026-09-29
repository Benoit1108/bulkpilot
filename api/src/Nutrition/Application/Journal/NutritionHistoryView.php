<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Target\MacroTargets;

final readonly class NutritionHistoryView
{
    /**
     * @param list<DayTotals>  $days  the days logged, the oldest first
     * @param list<WeekIntake> $weeks Monday first
     */
    public function __construct(
        public array $days,
        public array $weeks,
        public ?float $averageEnergyKcal,
        public ?float $averageProtein,
        public ?MacroTargets $target,
    ) {
    }
}
