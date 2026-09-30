<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Notifying\JournalDay;
use App\Nutrition\Application\Target\SetTargets;
use App\Nutrition\Application\Target\SetTargetsHandler;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class NotifyingTest extends NutritionTestCase
{
    public function testTheDayIsWhatWasEatenTheTargetAndTheLastEntry(): void
    {
        new SetTargetsHandler($this->targets, new EnergyCalculator(), $this->events, $this->clock)(
            new SetTargets(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day(), new MacroTargets(2350, 150, 280, 70), null),
        );
        $rice = $this->rice();
        $log = new LogFoodHandler($this->foods, $this->writer());
        $log(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $rice->id(), $this->day(), MealSlot::Lunch, 100));
        $this->clock->modify('+2 hours');
        $log(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $rice->id(), $this->day(), MealSlot::Dinner, 100));
        $log(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $rice->id(), $this->day('2026-09-27'), MealSlot::Dinner, 500));

        $day = new JournalDay($this->entries, $this->targets)->day($this->alex, $this->day());

        self::assertSame([712.0, 2350, '2026-09-28 12:00'], [round($day->eatenKcal, 1), $day->targetKcal, $day->lastLoggedAt?->format('Y-m-d H:i')]);
        $empty = new JournalDay($this->entries, $this->targets)->day($this->sam, $this->day());
        self::assertSame([0.0, null, null], [$empty->eatenKcal, $empty->targetKcal, $empty->lastLoggedAt]);
    }
}
