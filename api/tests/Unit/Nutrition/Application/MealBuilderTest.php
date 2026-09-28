<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Builder\BuildMeal;
use App\Nutrition\Application\Builder\BuildMealHandler;
use App\Nutrition\Application\Builder\MealBuilderView;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\CreateCustomFoodHandler;
use App\Nutrition\Application\Food\CustomFoodDetails;
use App\Nutrition\Application\Food\OwnBarcodes;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Journal\LogFoods;
use App\Nutrition\Application\Journal\LogFoodsHandler;
use App\Nutrition\Application\Journal\LogItem;
use App\Nutrition\Domain\Builder\MealBuilder;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Exception\InvalidBatch;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodState;
use App\Nutrition\Domain\Food\NutritionFacts;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\TargetSource;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class MealBuilderTest extends NutritionTestCase
{
    private string $chicken;
    private string $rice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chicken = $this->catalogueFood('36018', 'Poulet, filet, sauté/poêlé', new NutritionFacts(141, 30.1, 0, 2))->id()->value;
        $this->rice = $this->catalogueFood('9104', 'Riz blanc, cuit', new NutritionFacts(146, 2.9, 31.6, 0.5))->id()->value;
        $this->catalogueFood('17440', 'Huile de tournesol', new NutritionFacts(900, 0, 0, 100));
    }

    public function testWithoutATargetThereIsNothingToFill(): void
    {
        $view = $this->build();

        self::assertNull($view->goal);
        self::assertSame([], $view->proposals);
    }

    public function testItFillsWhatIsLeftOfTheDayFromStaples(): void
    {
        $this->setTarget(new MacroTargets(2500, 150, 300, 75));
        $this->log($this->rice, MealSlot::Breakfast, 500); // 730 kcal

        $view = $this->build();

        self::assertEqualsWithDelta(1770, $view->goal?->energyKcal, 0.001);
        self::assertEqualsWithDelta(150 - 14.5, $view->goal?->protein, 0.001);
        self::assertNotEmpty($view->proposals);
        self::assertSame('Poulet, filet, sauté/poêlé', $view->proposals[0]->items[0]->name);
    }

    public function testASlotIsFilledUpToItsShareOfTheTarget(): void
    {
        $this->setTarget(new MacroTargets(3000, 150, 400, 88));
        $this->mealSplits->save(MealSplit::of($this->alex, ['breakfast' => 20, 'lunch' => 40, 'dinner' => 40]));
        $this->log($this->chicken, MealSlot::Lunch, 200); // 282 kcal of the 1200 planned
        $this->log($this->rice, MealSlot::Dinner, 1000);

        $lunch = $this->build(MealSlot::Lunch);
        self::assertSame(MealSlot::Lunch, $lunch->slot);
        self::assertEqualsWithDelta(1200 - 282, $lunch->goal?->energyKcal, 0.001);

        // A meal left out of the split takes whatever is left of the day.
        $snack = $this->build(MealSlot::Snack);
        self::assertEqualsWithDelta(3000 - 282 - 1460, $snack->goal?->energyKcal, 0.001);
    }

    public function testAMealNeverGetsMoreThanWhatIsLeftOfTheDay(): void
    {
        $this->setTarget(new MacroTargets(3000, 150, 400, 88));
        $this->log($this->rice, MealSlot::Lunch, 1800); // 2628 kcal, all at lunch

        $dinner = $this->build(MealSlot::Dinner);

        self::assertEqualsWithDelta(3000 - 2628, $dinner->goal?->energyKcal, 0.001, 'not the 900 kcal planned for dinner');
        self::assertEqualsWithDelta(0, $dinner->goal?->carbohydrate, 0.001, 'carbohydrate of the day already exceeded');
    }

    public function testSomeoneElsesFoodsAreNeverCandidates(): void
    {
        $this->setTarget(new MacroTargets(2500, 150, 300, 75));
        new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000005c1'), $this->sam, new CustomFoodDetails('Whey de Sam', null, FoodState::NotApplicable, 380, 80, 6, 4)));

        foreach ($this->build()->proposals as $proposal) {
            self::assertNotContains('Whey de Sam', array_map(static fn ($item): string => $item->name, $proposal->items));
        }
    }

    public function testTheUsersOwnFoodsAreCandidatesToo(): void
    {
        $this->setTarget(new MacroTargets(2500, 150, 300, 75));
        new CreateCustomFoodHandler($this->foods, new OwnBarcodes($this->foods))(new CreateCustomFood(new FoodId('00000000-0000-7000-8000-0000000005c1'), $this->alex, new CustomFoodDetails('Whey', null, FoodState::NotApplicable, 380, 80, 6, 4)));

        $names = [];
        foreach ($this->build()->proposals as $proposal) {
            $names[] = $proposal->items[0]->name;
        }

        self::assertContains('Whey', $names);
    }

    public function testAProposalIsLoggedInOneGo(): void
    {
        $logFoods = new LogFoodsHandler($this->foods, $this->writer(), $this->uuids);

        $logFoods(new LogFoods($this->alex, $this->day(), MealSlot::Dinner, [new LogItem(new FoodId($this->chicken), 150.0), new LogItem(new FoodId($this->rice), 250.0)]));

        self::assertCount(2, $this->entries->ofDay($this->alex, $this->day()));

        try {
            $logFoods(new LogFoods($this->alex, $this->day(), MealSlot::Dinner, []));
            self::fail('nothing to log');
        } catch (InvalidBatch) {
        }
        try {
            $logFoods(new LogFoods($this->alex, $this->day(), MealSlot::Dinner, array_fill(0, LogFoods::MAX_ITEMS + 1, new LogItem(new FoodId($this->rice), 10.0))));
            self::fail('too many at once');
        } catch (InvalidBatch) {
        }
        self::assertCount(2, $this->entries->entries, 'nothing logged by refused batches');

        $this->expectException(FoodNotFound::class);
        $logFoods(new LogFoods($this->alex, $this->day(), MealSlot::Dinner, [new LogItem(new FoodId('00000000-0000-7000-8000-0000000000ff'), 100.0)]));
    }

    private function build(?MealSlot $slot = null): MealBuilderView
    {
        return new BuildMealHandler($this->targets, $this->entries, $this->mealSplits, $this->foods, new MealBuilder())(new BuildMeal($this->alex, $this->day(), $slot));
    }

    private function setTarget(MacroTargets $targets): void
    {
        $this->targets->save(NutritionTarget::set(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day('2026-09-01'), $targets, TargetSource::Manual, null, null, $this->day('2026-09-01')->setTime(8, 0)));
    }

    private function log(string $foodId, MealSlot $slot, float $grams): void
    {
        $this->clock->modify('+1 minute');
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, new FoodId($foodId), $this->day(), $slot, $grams));
    }
}
