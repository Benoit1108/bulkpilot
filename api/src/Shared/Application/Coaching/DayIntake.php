<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

final readonly class DayIntake
{
    public function __construct(public \DateTimeImmutable $day, public float $energyKcal, public float $proteinGrams)
    {
    }
}
