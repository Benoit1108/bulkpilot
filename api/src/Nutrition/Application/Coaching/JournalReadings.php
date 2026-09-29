<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Coaching;

use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Coaching\DayIntake;
use App\Shared\Application\Coaching\NutritionReadings;
use App\Shared\Application\Coaching\TargetReading;
use App\Shared\Domain\Identifier\UserId;

/**
 * The journal as the Coach reads it (ADR-0028): each day's totals and the target in force.
 */
final readonly class JournalReadings implements NutritionReadings
{
    public function __construct(private JournalEntryRepository $entries, private NutritionTargetRepository $targets)
    {
    }

    public function intake(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = array_values($this->entries->dailyTotals($userId, $from->setTime(0, 0), $to->setTime(0, 0)));
        usort($days, static fn (DayTotals $a, DayTotals $b): int => $a->day <=> $b->day);

        return array_map(static fn (DayTotals $day): DayIntake => new DayIntake($day->day, $day->amounts->energyKcal, $day->amounts->protein), $days);
    }

    public function targetOn(UserId $userId, \DateTimeImmutable $day): ?TargetReading
    {
        $target = $this->targets->inForceOn($userId, $day->setTime(0, 0));

        return null === $target ? null : new TargetReading(
            $target->effectiveFrom(),
            $target->targets()->energyKcal,
            $target->targets()->proteinGrams,
            $target->estimate()?->inputs->weeklyWeightChangeKg,
        );
    }
}
