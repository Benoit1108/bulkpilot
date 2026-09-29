<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Exception\InconsistentTarget;
use App\Nutrition\Domain\Exception\InvalidEnergyInputs;
use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Nutrition\Domain\Exception\InvalidMealSplit;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetSet;
use App\Nutrition\Domain\Target\Sex;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TargetTest extends TestCase
{
    public function testTheEstimateFollowsMifflinStJeorStepByStep(): void
    {
        // Man, 30, 180 cm, 80 kg, active, 4 sessions a week, bulk at +0.25 kg a week.
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Male, 30, 180, 80, Lifestyle::Active, 4, Goal::Bulk, 0.25));

        self::assertEqualsWithDelta(1780.0, $estimate->basalMetabolicRate, 0.001); // 800 + 1125 − 150 + 5
        self::assertSame(1.5, $estimate->activityLevel);
        self::assertEqualsWithDelta(0.2, $estimate->trainingAllowance, 0.001);
        self::assertEqualsWithDelta(3026.0, $estimate->totalExpenditure, 0.001); // 1780 × 1.7
        self::assertEqualsWithDelta(275.0, $estimate->dailyAdjustment, 0.001); // 0.25 × 7700 ÷ 7
        self::assertTrue($estimate->targets->equals(new MacroTargets(3300, 144, 474, 92)));
    }

    public function testACutNeverGoesBelowTheSafetyFloorAndKeepsEnoughFat(): void
    {
        // Woman, 25, 165 cm, 60 kg, sedentary, cut at −0.5 kg a week: 1614 − 550 = 1064 kcal.
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Female, 25, 165, 60, Lifestyle::Sedentary, 0, Goal::Cut, -0.5));

        self::assertEqualsWithDelta(1345.25, $estimate->basalMetabolicRate, 0.001);
        self::assertSame(EnergyCalculator::MIN_DAILY_KCAL, $estimate->targets->energyKcal);
        self::assertSame(132, $estimate->targets->proteinGrams, '2.2 g/kg on a cut');
        self::assertSame(36, $estimate->targets->fatGrams, '0.6 g/kg beats 25 % of 1200 kcal');
        self::assertSame(87, $estimate->targets->carbohydrateGrams);
    }

    public function testAHeavyPersonOnACutGetsAConsistentEstimate(): void
    {
        // Woman, 40, 160 cm, 130 kg, sedentary, cut at −0.75 kg a week: 2.2 g/kg of protein alone
        // (286 g, 1144 kcal) plus the minimum fat would exceed the calories.
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Female, 40, 160, 130, Lifestyle::Sedentary, 0, Goal::Cut, -0.75));
        $targets = $estimate->targets;

        self::assertLessThanOrEqual($targets->energyKcal * (1 + MacroTargets::TOLERANCE), MacroTargets::energyOf($targets->proteinGrams, $targets->carbohydrateGrams, $targets->fatGrams));
        self::assertLessThan(286, $targets->proteinGrams, 'protein capped to what fits');
        self::assertGreaterThan(0, $targets->fatGrams);
    }

    public function testAVeryHighExpenditureIsCappedToAPlausibleTarget(): void
    {
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Male, 20, 250, 350, Lifestyle::VeryActive, 14, Goal::Bulk, 1.0));

        self::assertSame(MacroTargets::MAX_ENERGY_KCAL, $estimate->targets->energyKcal);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function starts(): iterable
    {
        yield 'tomorrow' => ['2026-09-29', true];
        yield 'today' => ['2026-09-28', true];
        yield 'yesterday: time zones behind the server' => ['2026-09-27', true];
        yield 'in two days: the tomorrow of a device already past midnight, ahead of the server' => ['2026-09-30', true];
        yield 'two days ago' => ['2026-09-26', false];
        yield 'in three days' => ['2026-10-01', false];
    }

    #[DataProvider('starts')]
    public function testATargetStartsTodayOrTomorrowNeverInThePast(string $start, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidJournalDay::class);
        }

        $target = NutritionTarget::set(new NutritionTargetId('00000000-0000-7000-8000-000000000071'), new UserId('00000000-0000-7000-8000-000000000001'), new \DateTimeImmutable($start), new MacroTargets(2500, 150, 300, 75), TargetSource::Manual, null, null, new \DateTimeImmutable('2026-09-28 10:00'));

        self::assertSame($start, $target->effectiveFrom()->format('Y-m-d'));
    }

    public function testTheTrainingAllowanceIsCapped(): void
    {
        $estimate = new EnergyCalculator()->estimate(new EnergyInputs(Sex::Male, 40, 175, 75, Lifestyle::Light, 14, Goal::Maintain, 0));

        self::assertSame(EnergyCalculator::MAX_TRAINING_ALLOWANCE, $estimate->trainingAllowance);
        self::assertSame(0.0, $estimate->dailyAdjustment);
        self::assertSame(120, $estimate->targets->proteinGrams, '1.6 g/kg to maintain');
    }

    /**
     * @return iterable<string, array{int, int, float, int, Goal, float}>
     */
    public static function implausibleInputs(): iterable
    {
        yield 'too young' => [12, 180, 80, 3, Goal::Maintain, 0];
        yield 'too old' => [121, 180, 80, 3, Goal::Maintain, 0];
        yield 'too short' => [30, 99, 80, 3, Goal::Maintain, 0];
        yield 'too tall' => [30, 251, 80, 3, Goal::Maintain, 0];
        yield 'too light' => [30, 180, 24, 3, Goal::Maintain, 0];
        yield 'too heavy' => [30, 180, 351, 3, Goal::Maintain, 0];
        yield 'too many sessions' => [30, 180, 80, 15, Goal::Maintain, 0];
        yield 'negative sessions' => [30, 180, 80, -1, Goal::Maintain, 0];
        yield 'bulk losing weight' => [30, 180, 80, 3, Goal::Bulk, -0.2];
        yield 'bulk too fast' => [30, 180, 80, 3, Goal::Bulk, 1.5];
        yield 'cut gaining weight' => [30, 180, 80, 3, Goal::Cut, 0.2];
        yield 'cut too fast' => [30, 180, 80, 3, Goal::Cut, -1.2];
        yield 'maintain changing weight' => [30, 180, 80, 3, Goal::Maintain, 0.1];
    }

    #[DataProvider('implausibleInputs')]
    public function testImplausibleInputsAreRejected(int $age, int $height, float $weight, int $sessions, Goal $goal, float $change): void
    {
        $this->expectException(InvalidEnergyInputs::class);

        new EnergyInputs(Sex::Male, $age, $height, $weight, Lifestyle::Active, $sessions, $goal, $change);
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function inconsistentTargets(): iterable
    {
        yield 'too low' => [900, 50, 100, 30];
        yield 'too high' => [7100, 300, 1000, 200];
        yield 'negative macro' => [2000, -10, 300, 80];
        yield 'macros do not add up' => [2500, 150, 200, 60]; // 1940 kcal
    }

    #[DataProvider('inconsistentTargets')]
    public function testMacrosMustAddUpToTheCalories(int $kcal, int $protein, int $carbohydrate, int $fat): void
    {
        $this->expectException(InconsistentTarget::class);

        new MacroTargets($kcal, $protein, $carbohydrate, $fat);
    }

    public function testASmallGapBetweenMacrosAndCaloriesIsTolerated(): void
    {
        $targets = new MacroTargets(2500, 150, 300, 75); // 2475 kcal: 1 % off

        self::assertSame(2475, MacroTargets::energyOf(150, 300, 75));
        self::assertSame(2500, $targets->energyKcal);
        self::assertTrue($targets->equals(new MacroTargets(2500, 150, 300, 75)));
        self::assertFalse($targets->equals(new MacroTargets(2500, 150, 301, 75)));
    }

    public function testSettingATargetRecordsWhereItComesFromAndWhatItReplaces(): void
    {
        $owner = new UserId('00000000-0000-7000-8000-000000000001');
        $now = new \DateTimeImmutable('2026-09-28 10:00');
        $first = NutritionTarget::set(new NutritionTargetId('00000000-0000-7000-8000-000000000071'), $owner, $now, new MacroTargets(2200, 140, 270, 62), TargetSource::Manual, null, null, $now);
        $second = NutritionTarget::set(new NutritionTargetId('00000000-0000-7000-8000-000000000072'), $owner, $now->modify('+1 day'), new MacroTargets(2350, 140, 300, 65), TargetSource::Coach, null, $first, $now);

        $event = $second->pullDomainEvents()[0];
        self::assertInstanceOf(NutritionTargetSet::class, $event);
        self::assertSame(2200, $event->previous?->energyKcal);
        self::assertSame(2350, $event->targets->energyKcal);
        self::assertSame(TargetSource::Coach, $event->source);
        self::assertSame($now, $event->occurredOn());
        self::assertSame('2026-09-29 00:00', $second->effectiveFrom()->format('Y-m-d H:i'));
        $firstEvent = $first->pullDomainEvents()[0];
        self::assertInstanceOf(NutritionTargetSet::class, $firstEvent);
        self::assertNull($firstEvent->previous?->energyKcal, 'the first target replaces nothing');
        self::assertSame($owner, $second->ownerId());
        self::assertSame($now, $second->setAt());
        self::assertNull($second->estimate());
    }

    public function testTheDefaultMealSplitSuitsABulk(): void
    {
        $split = MealSplit::defaultFor(new UserId('00000000-0000-7000-8000-000000000001'));

        self::assertSame(['breakfast' => 25, 'lunch' => 30, 'snack' => 0, 'gainer' => 15, 'dinner' => 30], $split->shares());
        self::assertSame(15, $split->shareOf(MealSlot::Gainer));
    }

    public function testTheMealSplitIsChangedAndMissingSlotsAreZero(): void
    {
        $split = MealSplit::of(new UserId('00000000-0000-7000-8000-000000000001'), ['breakfast' => 20, 'lunch' => 40, 'dinner' => 40]);
        self::assertSame(0, $split->shareOf(MealSlot::Snack));

        $split->change(['breakfast' => 20, 'lunch' => 30, 'snack' => 10, 'gainer' => 10, 'dinner' => 30]);
        self::assertSame(10, $split->shareOf(MealSlot::Snack));
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function invalidSplits(): iterable
    {
        yield 'not 100' => [['breakfast' => 30, 'lunch' => 30, 'dinner' => 30]];
        yield 'negative' => [['breakfast' => -10, 'lunch' => 60, 'dinner' => 50]];
        yield 'above 100' => [['breakfast' => 110, 'lunch' => -10]];
        yield 'unknown slot' => [['breakfast' => 50, 'brunch' => 50]];
    }

    /**
     * @param array<string, int> $shares
     */
    #[DataProvider('invalidSplits')]
    public function testInvalidSplitsAreRejected(array $shares): void
    {
        $this->expectException(InvalidMealSplit::class);

        MealSplit::of(new UserId('00000000-0000-7000-8000-000000000001'), $shares);
    }

    public function testOtherCaloriesKeepTheProteinAndFatAndMakeUpTheRestInCarbohydrates(): void
    {
        $targets = new MacroTargets(2800, 150, 360, 80);

        $more = $targets->withEnergy(2950);
        $less = $targets->withEnergy(1320);

        self::assertSame([2950, 150, 408, 80], [$more->energyKcal, $more->proteinGrams, $more->carbohydrateGrams, $more->fatGrams]);
        self::assertSame(2952, MacroTargets::energyOf($more->proteinGrams, $more->carbohydrateGrams, $more->fatGrams), 'to the nearest gram');
        self::assertSame([1320, 150, 0, 80], [$less->energyKcal, $less->proteinGrams, $less->carbohydrateGrams, $less->fatGrams], 'the protein and fat make it all');
    }

    public function testOtherCaloriesBelowTheProteinAndFatAreRefused(): void
    {
        $this->expectException(InconsistentTarget::class);

        new MacroTargets(2800, 150, 360, 80)->withEnergy(1300);
    }

    public function testOtherCaloriesOutOfBoundsAreRefused(): void
    {
        $this->expectException(InconsistentTarget::class);

        new MacroTargets(7000, 150, 1420, 80)->withEnergy(7100);
    }
}
