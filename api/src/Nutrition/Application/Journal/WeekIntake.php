<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

/**
 * A week's mean intake over the days logged in it (what the weight is compared with, spec §56).
 */
final readonly class WeekIntake
{
    public function __construct(public \DateTimeImmutable $week, public float $averageEnergyKcal, public float $averageProtein, public int $days)
    {
    }
}
