<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Application\Target\TargetView;
use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class DayView
{
    /**
     * @param list<MealView> $meals every slot, in chronological order
     */
    public function __construct(
        public \DateTimeImmutable $day,
        public NutrientAmounts $totals,
        public ?TargetView $target,
        public array $meals,
    ) {
    }
}
