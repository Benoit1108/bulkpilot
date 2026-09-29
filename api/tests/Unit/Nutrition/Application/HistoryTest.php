<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Journal\GetNutritionHistory;
use App\Nutrition\Application\Journal\GetNutritionHistoryHandler;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Journal\NutritionHistoryView;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Domain\Calendar\Period;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class HistoryTest extends NutritionTestCase
{
    private function log(string $day, float $grams): void
    {
        $rice = [] === $this->foods->foods ? $this->rice() : array_values($this->foods->foods)[0];
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $rice->id(), $this->day($day), MealSlot::Lunch, $grams));
    }

    private function history(Period $period): NutritionHistoryView
    {
        return new GetNutritionHistoryHandler($this->entries, $this->targets)(new GetNutritionHistory($this->alex, $period, $this->day('2026-09-28')));
    }

    public function testTheCompleteDaysLoggedMakeTheDaysTheWeeksAndTheMean(): void
    {
        $this->log('2026-09-15', 200);
        $this->log('2026-09-15', 100);
        $this->log('2026-09-22', 100);
        $this->log('2026-09-28', 500);
        $this->targets->save(NutritionTarget::set(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day('2026-09-01'), new MacroTargets(2600, 130, 330, 80), TargetSource::Manual, null, null, $this->day('2026-09-01')->setTime(8, 0)));

        $history = $this->history(Period::Month);

        self::assertSame(['2026-09-15', '2026-09-22'], array_map(static fn ($day): string => $day->day->format('Y-m-d'), $history->days), 'today is under way');
        self::assertSame([1068.0, 356.0], array_map(static fn ($day): float => round($day->amounts->energyKcal, 1), $history->days));
        self::assertSame([['2026-09-14', 1068.0, 1], ['2026-09-21', 356.0, 1]], array_map(static fn ($week): array => [$week->week->format('Y-m-d'), $week->averageEnergyKcal, $week->days], $history->weeks));
        self::assertSame([712.0, 14.8], [$history->averageEnergyKcal, $history->averageProtein]);
        self::assertSame(2600, $history->target?->energyKcal);
    }

    public function testEverythingReachesTheOldestDayAndNothingIsNothing(): void
    {
        self::assertSame([[], null], [$this->history(Period::All)->days, $this->history(Period::All)->averageEnergyKcal]);

        $this->log('2025-08-20', 100);
        $this->log('2026-09-27', 100);

        self::assertCount(2, $this->history(Period::All)->days, 'more than a year back');
        self::assertCount(1, $this->history(Period::Year)->days);
    }
}
