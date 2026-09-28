<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Application\Target\TargetView;
use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * Averages over the complete days that were logged (today excluded, it is still going on): a day
 * without any entry is "not logged", not "zero eaten", so it would drag the average down for nothing.
 */
final readonly class AveragesView
{
    public function __construct(
        public int $days,
        public int $loggedDays,
        public ?NutrientAmounts $dailyAverage,
        public ?TargetView $currentTarget,
    ) {
    }
}
