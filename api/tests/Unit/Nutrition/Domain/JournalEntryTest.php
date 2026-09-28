<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InvalidQuantity;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{
    private UserId $owner;

    protected function setUp(): void
    {
        $this->owner = new UserId('00000000-0000-7000-8000-000000000001');
    }

    public function testAnEntryFreezesTheFoodValuesAtLoggingTime(): void
    {
        $food = Food::createCustom(new FoodId('00000000-0000-7000-8000-00000000f00d'), $this->owner, 'Pâtes', 'Barilla', FoodState::Cooked, new NutritionFacts(157, 5.8, 30.6, 0.9), []);
        $entry = $this->log(LoggedFood::snapshotOf($food), 200);

        $food->edit('Pâtes', 'Barilla', FoodState::Cooked, new NutritionFacts(999 / 2, 1, 1, 1), []);

        self::assertSame('Pâtes (Barilla)', $entry->food()->name);
        self::assertSame(FoodState::Cooked, $entry->food()->state);
        self::assertEqualsWithDelta(314.0, $entry->amounts()->energyKcal, 0.001);
        self::assertEqualsWithDelta(61.2, $entry->amounts()->carbohydrate, 0.001);
        self::assertSame('2026-09-28 00:00:00', $entry->day()->format('Y-m-d H:i:s'));
        self::assertSame(MealSlot::Lunch, $entry->slot());
        self::assertTrue($entry->belongsTo($this->owner));
        self::assertSame($this->owner, $entry->ownerId());
        self::assertSame('2026-09-28 12:30', $entry->loggedAt()->format('Y-m-d H:i'));
    }

    public function testAnEntryIsCorrectedOrMoved(): void
    {
        $entry = $this->log($this->rice(), 80);

        $entry->correct(120.04, MealSlot::Dinner, new \DateTimeImmutable('2026-09-27 21:00'));

        self::assertSame(120.0, $entry->grams());
        self::assertSame(MealSlot::Dinner, $entry->slot());
        self::assertSame('2026-09-27 00:00', $entry->day()->format('Y-m-d H:i'));
    }

    #[TestWith([0])]
    #[TestWith([-5])]
    #[TestWith([5000.1])]
    public function testQuantitiesArePlausible(float $grams): void
    {
        $this->expectException(InvalidQuantity::class);

        $this->log($this->rice(), $grams);
    }

    private function rice(): LoggedFood
    {
        return new LoggedFood(null, 'Riz', FoodState::Raw, new NutritionFacts(356, 7.4, 78.6, 0.7)->reference());
    }

    private function log(LoggedFood $food, float $grams): JournalEntry
    {
        return JournalEntry::log(
            new JournalEntryId('00000000-0000-7000-8000-00000000e001'),
            $this->owner,
            new \DateTimeImmutable('2026-09-28 12:30'),
            MealSlot::Lunch,
            $food,
            $grams,
            new \DateTimeImmutable('2026-09-28 12:30'),
        );
    }
}
