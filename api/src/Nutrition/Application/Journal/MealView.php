<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\MealSlot;

final readonly class MealView
{
    /**
     * @param int             $share            percent of the day's calories planned for this meal
     * @param int|null        $targetEnergyKcal share of the day's target, when there is one
     * @param list<EntryView> $entries
     */
    public function __construct(
        public MealSlot $slot,
        public int $share,
        public ?int $targetEnergyKcal,
        public NutrientAmounts $totals,
        public array $entries,
    ) {
    }
}
