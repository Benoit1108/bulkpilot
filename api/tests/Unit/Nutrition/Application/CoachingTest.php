<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Application;

use App\Nutrition\Application\Coaching\CoachCalorieTargets;
use App\Nutrition\Application\Coaching\JournalReadings;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoodHandler;
use App\Nutrition\Application\Target\EstimateRequest;
use App\Nutrition\Application\Target\SetTargets;
use App\Nutrition\Application\Target\SetTargetsHandler;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetSet;
use App\Nutrition\Domain\Target\Sex;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Application\Coaching\DayIntake;
use App\Shared\Application\Coaching\TargetChanged;
use App\Shared\Application\Coaching\TargetUnreachable;
use App\Tests\Support\Nutrition\NutritionTestCase;

final class CoachingTest extends NutritionTestCase
{
    private function log(string $day, float $grams): void
    {
        $rice = [] === $this->foods->foods ? $this->rice() : array_values($this->foods->foods)[0];
        new LogFoodHandler($this->foods, $this->writer())(new LogFood(new JournalEntryId($this->uuids->generate()), $this->alex, $rice->id(), $this->day($day), MealSlot::Lunch, $grams));
    }

    private function set(string $from, MacroTargets $targets, bool $estimated = false): void
    {
        $this->clock->modify('+1 second');
        $estimate = $estimated ? new EstimateRequest(Sex::Male, new \DateTimeImmutable('1996-01-15'), 180, 80, Lifestyle::Active, 4, Goal::Bulk, 0.25) : null;
        new SetTargetsHandler($this->targets, new EnergyCalculator(), $this->events, $this->clock)(
            new SetTargets(new NutritionTargetId($this->uuids->generate()), $this->alex, $this->day($from), $targets, $estimate),
        );
    }

    private function calories(): CoachCalorieTargets
    {
        return new CoachCalorieTargets($this->targets, $this->events, $this->uuids, $this->clock);
    }

    public function testTheIntakeReadsTheDaysLoggedInTheRangeTheOldestFirst(): void
    {
        $this->log('2026-09-26', 500);
        $this->log('2026-09-20', 100);
        $this->log('2026-09-20', 100);
        $this->log('2026-09-19', 100);
        $this->log('2026-09-27', 100);

        $readings = new JournalReadings($this->entries, $this->targets);
        $days = $readings->intake($this->alex, $this->day('2026-09-20'), $this->day('2026-09-26'));

        self::assertSame(['2026-09-20', '2026-09-26'], array_map(static fn (DayIntake $day): string => $day->day->format('Y-m-d'), $days), 'both ends included, the days around left out');
        self::assertSame([712.0, 1780.0], array_map(static fn (DayIntake $day): float => round($day->energyKcal, 1), $days));
        self::assertSame([14.8, 37.0], array_map(static fn (DayIntake $day): float => round($day->proteinGrams, 1), $days));
        self::assertSame([], $readings->intake($this->sam, $this->day('2026-09-20'), $this->day('2026-09-26')), 'someone else\'s journal is not read');
        self::assertNull($readings->targetOn($this->alex, $this->day('2026-09-26')), 'no target set');
    }

    public function testTheTargetReadCarriesThePaceItWasEstimatedFor(): void
    {
        $this->set('2026-09-28', new MacroTargets(3300, 144, 474, 92), estimated: true);
        $readings = new JournalReadings($this->entries, $this->targets);

        $target = $readings->targetOn($this->alex, $this->day('2026-09-28'));

        self::assertSame(['2026-09-28', 3300, 144, 0.25], [$target?->effectiveFrom->format('Y-m-d'), $target?->energyKcal, $target?->proteinGrams, $target?->weeklyChangeKg]);
        self::assertNull($readings->targetOn($this->alex, $this->day('2026-09-27')), 'not yet in force on the 27th');
        self::assertNull($readings->targetOn($this->sam, $this->day('2026-09-28')), 'someone else\'s target is not read');

        $this->set('2026-09-29', new MacroTargets(2800, 150, 360, 80));
        self::assertNull($readings->targetOn($this->alex, $this->day('2026-09-29'))?->weeklyChangeKg, 'typed by hand: no pace');
    }

    public function testAnAcceptedProposalMovesTheCarbohydratesFromTomorrowAsTheCoachs(): void
    {
        $this->set('2026-09-28', new MacroTargets(3300, 144, 474, 92), estimated: true);

        $this->calories()->adjust($this->alex, 3300, 150, $this->day('2026-09-29'));

        $new = $this->targets->inForceOn($this->alex, $this->day('2026-09-29'));
        self::assertNotNull($new);
        $macros = $new->targets();
        self::assertSame([3450, 144, 512, 92], [$macros->energyKcal, $macros->proteinGrams, $macros->carbohydrateGrams, $macros->fatGrams], '+150 kcal ≈ +38 g of carbohydrates');
        self::assertSame(TargetSource::Coach, $new->source());
        self::assertSame(0.25, $new->estimate()?->inputs->weeklyWeightChangeKg, 'the pace aimed at is kept');
        self::assertSame(3300, $this->targets->inForceOn($this->alex, $this->day('2026-09-28'))?->targets()->energyKcal, 'today keeps its target');
        $event = end($this->events->published);
        self::assertInstanceOf(NutritionTargetSet::class, $event);
        self::assertSame([3300, 3450, TargetSource::Coach], [$event->previous?->energyKcal, $event->targets->energyKcal, $event->source]);
    }

    public function testTheCarbohydratesMakeUpTheNewCaloriesExactly(): void
    {
        // 2 760 kcal of macros for 2 800: within the tolerance, but a shift of the carbohydrates
        // alone would carry the gap on; the new ones make up 2 700 exactly.
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80));

        $this->calories()->adjust($this->alex, 2800, -100, $this->day('2026-09-29'));

        $macros = $this->targets->inForceOn($this->alex, $this->day('2026-09-29'))?->targets();
        self::assertSame([2700, 150, 345, 80], [$macros?->energyKcal, $macros?->proteinGrams, $macros?->carbohydrateGrams, $macros?->fatGrams]);
    }

    public function testATargetNutritionWouldRefuseIsUnreachable(): void
    {
        // 1 050 kcal − 150: below the 1 000 kcal of a target.
        $this->set('2026-09-28', new MacroTargets(1050, 60, 130, 30));

        $this->expectException(TargetUnreachable::class);
        $this->calories()->adjust($this->alex, 1050, -150, $this->day('2026-09-29'));
    }

    public function testTooFewCaloriesForTheProteinAndFatAreUnreachable(): void
    {
        // Protein and fat alone give 1 950 kcal: 1 850 leaves nothing for carbohydrates.
        $this->set('2026-09-28', new MacroTargets(2000, 150, 20, 150));

        $this->expectException(TargetUnreachable::class);
        $this->calories()->adjust($this->alex, 2000, -150, $this->day('2026-09-29'));
    }

    public function testATargetAlreadyPlannedForThatDayIsNotTheOneProposedAgainst(): void
    {
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80));
        $this->set('2026-09-29', new MacroTargets(2900, 150, 385, 80));

        $this->expectException(TargetChanged::class);
        $this->calories()->adjust($this->alex, 2800, 150, $this->day('2026-09-29'));
    }

    public function testAProposalMadeAgainstAnotherTargetIsRefused(): void
    {
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80));

        $this->expectException(TargetChanged::class);
        $this->calories()->adjust($this->alex, 2700, 150, $this->day('2026-09-29'));
    }

    public function testThereIsNothingToAdjustWithoutATarget(): void
    {
        $this->expectException(TargetChanged::class);
        $this->calories()->adjust($this->alex, 2800, 150, $this->day('2026-09-29'));
    }

    public function testSomeoneElsesTargetIsNeverAdjusted(): void
    {
        $this->set('2026-09-28', new MacroTargets(2800, 150, 360, 80));

        $this->expectException(TargetChanged::class);
        $this->calories()->adjust($this->sam, 2800, 150, $this->day('2026-09-29'));
    }
}
