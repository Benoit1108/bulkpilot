<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Journal\CorrectEntry;
use App\Nutrition\Application\Journal\CorrectEntryHandler;
use App\Nutrition\Application\Journal\DayView;
use App\Nutrition\Application\Journal\GetAverages;
use App\Nutrition\Application\Journal\GetAveragesHandler;
use App\Nutrition\Application\Journal\GetDay;
use App\Nutrition\Application\Journal\GetDayHandler;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Journal\RemoveEntry;
use App\Nutrition\Application\Journal\RemoveEntryHandler;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Nutrition\Domain\Exception\InvalidPeriod;
use App\Nutrition\Domain\Exception\JournalEntryNotFound;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Journal\JournalDays;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\TargetSource;
use App\Tests\Support\Nutrition\NutritionTestCase;
use PHPUnit\Framework\Attributes\TestWith;

final class JournalTest extends NutritionTestCase
{
    public function testADayListsEveryMealWithItsShareOfTheTarget(): void
    {
        $rice = $this->rice();
        $this->setTarget('2026-09-01', new MacroTargets(3000, 150, 400, 88));
        $first = $this->logFood($rice->id(), MealSlot::Lunch, 100);
        $this->logFood($rice->id(), MealSlot::Lunch, 50);
        $this->logFood($rice->id(), MealSlot::Gainer, 200);
        $this->logFood($rice->id(), MealSlot::Lunch, 100, '2026-09-27');

        $day = $this->getDay('2026-09-28');

        self::assertSame(['breakfast', 'lunch', 'snack', 'gainer', 'dinner'], array_map(static fn ($meal): string => $meal->slot->value, $day->meals));
        $lunch = $day->meals[1];
        self::assertCount(2, $lunch->entries);
        self::assertSame($first->value, $lunch->entries[0]->id);
        self::assertSame(30, $lunch->share);
        self::assertSame(900, $lunch->targetEnergyKcal);
        self::assertEqualsWithDelta(534.0, $lunch->totals->energyKcal, 0.001);
        self::assertSame(0.0, $day->meals[0]->totals->energyKcal);
        self::assertEqualsWithDelta(356.0 * 3.5, $day->totals->energyKcal, 0.001);
        self::assertSame(3000, $day->target?->targets->energyKcal);
    }

    public function testADayWithoutTargetFollowsTheUsersOwnSplit(): void
    {
        $this->mealSplits->save(MealSplit::of($this->alex, ['breakfast' => 20, 'lunch' => 40, 'dinner' => 40]));

        $day = $this->getDay('2026-09-28');

        self::assertNull($day->target);
        self::assertNull($day->meals[1]->targetEnergyKcal);
        self::assertSame([20, 40, 0, 0, 40], array_map(static fn ($meal): int => $meal->share, $day->meals));
    }

    public function testEachDayIsJudgedAgainstTheTargetOfItsTime(): void
    {
        $this->setTarget('2026-09-01', new MacroTargets(2800, 150, 360, 80));
        $this->setTarget('2026-09-20', new MacroTargets(3000, 150, 400, 88));

        self::assertSame(2800, $this->getDay('2026-09-19')->target?->targets->energyKcal);
        self::assertSame(3000, $this->getDay('2026-09-20')->target?->targets->energyKcal);
        self::assertNull($this->getDay('2026-08-31')->target);
    }

    public function testOnlyTheOwnerSeesTheirEntriesAndOnlyVisibleFoodsAreLogged(): void
    {
        $this->logFood($this->rice()->id(), MealSlot::Lunch, 100);

        self::assertSame(0.0, new GetDayHandler($this->entries, $this->targets, $this->mealSplits)(new GetDay($this->sam, $this->day()))->totals->energyKcal);

        $this->expectException(FoodNotFound::class);
        $this->logFood(new FoodId('00000000-0000-7000-8000-0000000000ff'), MealSlot::Lunch, 100);
    }

    public function testAnEntryIsCorrectedMovedAndRemovedByItsOwner(): void
    {
        $entryId = $this->logFood($this->rice()->id(), MealSlot::Lunch, 100);
        $correct = new CorrectEntryHandler($this->entries, new JournalDays(), $this->clock);

        $correct(new CorrectEntry($entryId, $this->alex, $this->day('2026-09-27'), MealSlot::Dinner, 150));
        self::assertEqualsWithDelta(534.0, $this->getDay('2026-09-27')->meals[4]->totals->energyKcal, 0.001);
        self::assertSame([], $this->getDay('2026-09-28')->meals[1]->entries);

        try {
            new RemoveEntryHandler($this->entries)(new RemoveEntry($entryId, $this->sam));
            self::fail('someone else\'s entry is not found');
        } catch (JournalEntryNotFound) {
        }

        new RemoveEntryHandler($this->entries)(new RemoveEntry($entryId, $this->alex));
        self::assertSame([], $this->entries->entries);
    }

    public function testSomeoneElsesEntryCannotBeCorrected(): void
    {
        $entryId = $this->logFood($this->rice()->id(), MealSlot::Lunch, 100);

        $this->expectException(JournalEntryNotFound::class);
        new CorrectEntryHandler($this->entries, new JournalDays(), $this->clock)(new CorrectEntry($entryId, $this->sam, $this->day(), MealSlot::Dinner, 50));
    }

    public function testAnEntryCannotBeMovedFarFromToday(): void
    {
        $entryId = $this->logFood($this->rice()->id(), MealSlot::Lunch, 100);

        $this->expectException(InvalidJournalDay::class);
        new CorrectEntryHandler($this->entries, new JournalDays(), $this->clock)(new CorrectEntry($entryId, $this->alex, $this->day('2026-10-05'), MealSlot::Dinner, 50));
    }

    #[TestWith(['2026-09-30'])]
    #[TestWith(['2016-09-01'])]
    public function testDaysFarFromTodayAreRefused(string $day): void
    {
        $this->expectException(InvalidJournalDay::class);

        $this->logFood($this->rice()->id(), MealSlot::Lunch, 100, $day);
    }

    public function testTomorrowIsAcceptedForTimeZonesAheadOfTheServer(): void
    {
        $this->logFood($this->rice()->id(), MealSlot::Breakfast, 100, '2026-09-29');

        self::assertCount(1, $this->getDay('2026-09-29')->meals[0]->entries);
    }

    public function testAveragesSkipTheDaysNothingWasLogged(): void
    {
        $rice = $this->rice();
        $this->setTarget('2026-09-01', new MacroTargets(3000, 150, 400, 88));
        $this->logFood($rice->id(), MealSlot::Lunch, 5000, '2026-09-28'); // today: still going on, left out
        $this->logFood($rice->id(), MealSlot::Lunch, 100, '2026-09-27');
        $this->logFood($rice->id(), MealSlot::Lunch, 300, '2026-09-21'); // first day of the 7
        $this->logFood($rice->id(), MealSlot::Lunch, 1000, '2026-09-20'); // just outside

        $week = new GetAveragesHandler($this->entries, $this->targets)(new GetAverages($this->alex, $this->day(), 7));

        self::assertSame(7, $week->days);
        self::assertSame(2, $week->loggedDays);
        self::assertEqualsWithDelta(712.0, $week->dailyAverage?->energyKcal, 0.001); // (356 + 1068) ÷ 2
        self::assertSame(3000, $week->currentTarget?->targets->energyKcal);

        $empty = new GetAveragesHandler($this->entries, $this->targets)(new GetAverages($this->sam, $this->day(), 30));
        self::assertSame(0, $empty->loggedDays);
        self::assertNull($empty->dailyAverage);
        self::assertNull($empty->currentTarget);
    }

    public function testAveragesAreOverSevenOrThirtyDays(): void
    {
        $this->expectException(InvalidPeriod::class);

        new GetAveragesHandler($this->entries, $this->targets)(new GetAverages($this->alex, $this->day(), 365));
    }

    private function logFood(FoodId $foodId, MealSlot $slot, float $grams, string $day = '2026-09-28'): JournalEntryId
    {
        $this->clock->modify('+1 minute');
        $entryId = new JournalEntryId($this->uuids->generate());
        new LogFoodHandler($this->foods, $this->writer())(new LogFood($entryId, $this->alex, $foodId, $this->day($day), $slot, $grams));

        return $entryId;
    }

    private function getDay(string $day): DayView
    {
        return new GetDayHandler($this->entries, $this->targets, $this->mealSplits)(new GetDay($this->alex, $this->day($day)));
    }

    /**
     * As it was set back then, on the day it started.
     */
    private function setTarget(string $from, MacroTargets $targets): void
    {
        $this->targets->save(NutritionTarget::set(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day($from), $targets, TargetSource::Manual, null, null, $this->day($from)->setTime(8, 0)));
    }
}
