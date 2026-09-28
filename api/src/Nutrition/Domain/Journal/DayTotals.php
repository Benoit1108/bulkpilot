<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class DayTotals
{
    public function __construct(public \DateTimeImmutable $day, public NutrientAmounts $amounts)
    {
    }
}
