<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition\Domain;

use App\Nutrition\Domain\Builder\FoodRole;
use App\Nutrition\Domain\Builder\MacroGoal;
use App\Nutrition\Domain\Builder\MealBuilder;
use App\Nutrition\Domain\Builder\MealProposal;
use App\Nutrition\Domain\Builder\ProposedPortion;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Food\NutritionFacts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MealBuilderTest extends TestCase
{
    private int $ids = 0;

    /**
     * @return iterable<string, array{NutritionFacts, ?FoodRole}>
     */
    public static function roles(): iterable
    {
        yield 'chicken' => [new NutritionFacts(141, 30.1, 0, 2), FoodRole::Protein];
        yield 'egg' => [new NutritionFacts(140, 12.5, 0.7, 9.8), FoodRole::Protein];
        yield 'rice' => [new NutritionFacts(146, 2.9, 31.6, 0.5), FoodRole::Carbohydrate];
        yield 'oil' => [new NutritionFacts(900, 0, 0, 100), FoodRole::Fat];
        yield 'milk' => [new NutritionFacts(46, 3.2, 4.8, 1.5), FoodRole::Side];
        yield 'water' => [new NutritionFacts(0, 0, 0, 0), null];
        yield 'broth' => [new NutritionFacts(10, 0.5, 1, 0.3), null];
    }

    #[DataProvider('roles')]
    public function testAFoodsRoleComesFromWhereItsCaloriesComeFrom(NutritionFacts $facts, ?FoodRole $role): void
    {
        self::assertSame($role, FoodRole::of($facts));
    }

    public function testItProposesAMealCloseToTheSpecExample(): void
    {
        // Spec §28: 820 kcal, 42 g protein, 105 g carbohydrate, 25 g fat left.
        $proposals = new MealBuilder()->propose(new MacroGoal(820, 42, 105, 25), [
            $this->food('Poulet, filet, cuit', 141, 30.1, 0, 2),
            $this->food('Riz blanc, cuit', 146, 2.9, 31.6, 0.5),
            $this->food('Huile de tournesol', 900, 0, 0, 100),
            $this->food('Banane', 90, 1.1, 20, 0.3),
        ]);

        self::assertNotEmpty($proposals);
        $best = $proposals[0];
        self::assertSame('Poulet, filet, cuit', $best->portions[0]->food->name());
        self::assertLessThan(80, $best->gap, 'within a few grams of every macro');
        self::assertEqualsWithDelta(820, $best->totals->energyKcal, 820 * 0.1);
        self::assertEqualsWithDelta(42, $best->totals->protein, 6);
        self::assertEqualsWithDelta(105, $best->totals->carbohydrate, 12);
        self::assertEqualsWithDelta(25, $best->totals->fat, 5);
        foreach ($best->portions as $portion) {
            self::assertSame(0.0, fmod($portion->grams, $portion->role->rounding()), 'kitchen-scale grams');
            [$min, $max] = $portion->role->gramRange();
            self::assertGreaterThanOrEqual($min, $portion->grams);
            self::assertLessThanOrEqual($max, $portion->grams);
        }
    }

    public function testTheSpecExampleGivesExactlyTheseGrams(): void
    {
        // Kitchen-scale grams fitted on the macros in kcal: a change in the fit changes them.
        $proposals = new MealBuilder()->propose(new MacroGoal(820, 42, 105, 25), [
            $this->food('Poulet, filet, cuit', 141, 30.1, 0, 2),
            $this->food('Riz blanc, cuit', 146, 2.9, 31.6, 0.5),
            $this->food('Huile de tournesol', 900, 0, 0, 100),
            $this->food('Eau', 0, 0, 0, 0),
        ]);

        self::assertSame([['Poulet, filet, cuit 110', 'Riz blanc, cuit 330', 'Huile de tournesol 21']], self::describe($proposals));
        self::assertEqualsWithDelta(4.1851, $proposals[0]->gap, 0.0001);
        self::assertEqualsWithDelta(825.9, $proposals[0]->totals->energyKcal, 0.001);
    }

    public function testOnlyTheFirstSixCandidatesOfARoleAreTried(): void
    {
        $habits = array_map(fn (int $n): Food => $this->food('Blanc d’oeuf '.$n, 48, 11, 0.7, 0.2), range(1, MealBuilder::CANDIDATES_PER_ROLE));
        $egg = $this->food('Oeuf', 140, 12.5, 0.7, 9.8);
        $rice = $this->food('Riz', 146, 2.9, 31.6, 0.5);
        $goal = new MacroGoal(820, 42, 105, 25);

        // A seventh protein that fits far better is not tried: the user's habits come first.
        self::assertNotContains('Oeuf', self::mains(new MealBuilder()->propose($goal, [...$habits, $egg, $rice])));
        self::assertSame('Oeuf', self::mains(new MealBuilder()->propose($goal, [$egg, $rice, ...$habits]))[0]);
    }

    public function testAGoalOfOneHundredKcalIsStillBuilt(): void
    {
        // 50 g of chicken and 50 g of milk: 93.5 kcal.
        $foods = [$this->food('Poulet', 141, 30.1, 0, 2), $this->food('Lait', 46, 3.2, 4.8, 1.5)];

        self::assertNotEmpty(new MealBuilder()->propose(new MacroGoal(MealBuilder::MIN_GOAL_KCAL, 15, 5, 2), $foods));
        self::assertSame([], new MealBuilder()->propose(new MacroGoal(99, 15, 5, 2), $foods), 'just under: nearly reached');
    }

    public function testEachRoleHasItsRangeAndRounding(): void
    {
        self::assertSame([[50.0, 400.0], [40.0, 500.0], [5.0, 40.0], [50.0, 300.0]], array_map(static fn (FoodRole $role): array => $role->gramRange(), [FoodRole::Protein, FoodRole::Carbohydrate, FoodRole::Fat, FoodRole::Side]));
        self::assertSame([5.0, 5.0, 1.0, 5.0], array_map(static fn (FoodRole $role): float => $role->rounding(), [FoodRole::Protein, FoodRole::Carbohydrate, FoodRole::Fat, FoodRole::Side]));
    }

    /**
     * @return iterable<string, array{NutritionFacts, ?FoodRole}>
     */
    public static function roleEdges(): iterable
    {
        yield 'exactly 35 % from protein' => [new NutritionFacts(100, 8.75, 16.25, 0), FoodRole::Protein];
        yield 'exactly 70 % from fat' => [new NutritionFacts(90, 0, 6.75, 7), FoodRole::Fat];
        yield 'exactly 55 % from carbohydrate' => [new NutritionFacts(100, 0, 13.75, 5), FoodRole::Carbohydrate];
        yield 'exactly 20 kcal' => [new NutritionFacts(20, 0, 5, 0), FoodRole::Carbohydrate];
        yield 'calories without macros' => [new NutritionFacts(25, 0, 0, 0), null];
    }

    #[DataProvider('roleEdges')]
    public function testTheRoleThresholdsAreInclusive(NutritionFacts $facts, ?FoodRole $role): void
    {
        self::assertSame($role, FoodRole::of($facts));
    }

    public function testAGoalAlreadyExceededIsZeroNotNegative(): void
    {
        $goal = new MacroGoal(-50, 10, 10, -3);

        self::assertSame([0.0, 0.0], [$goal->energyKcal, $goal->fat]);
    }

    public function testProposalsAreAlternativesAroundDifferentMainFoods(): void
    {
        $proposals = new MealBuilder()->propose(new MacroGoal(900, 50, 110, 25), [
            $this->food('Poulet', 141, 30.1, 0, 2),
            $this->food('Oeuf', 140, 12.5, 0.7, 9.8),
            $this->food('Thon', 116, 26, 0, 1),
            $this->food('Skyr', 63, 11, 4, 0.2),
            $this->food('Riz', 146, 2.9, 31.6, 0.5),
            $this->food('Pâtes', 150, 5.5, 30, 0.9),
            $this->food('Huile', 900, 0, 0, 100),
        ]);

        self::assertCount(MealBuilder::MAX_PROPOSALS, $proposals);
        $mains = array_map(static fn ($proposal): string => $proposal->portions[0]->food->name(), $proposals);
        self::assertSame($mains, array_values(array_unique($mains)));
        self::assertLessThanOrEqual($proposals[1]->gap, $proposals[0]->gap);
    }

    public function testTheSameDataAlwaysGiveTheSameProposals(): void
    {
        $foods = [
            $this->food('Poulet', 141, 30.1, 0, 2),
            $this->food('Riz', 146, 2.9, 31.6, 0.5),
            $this->food('Pâtes', 150, 5.5, 30, 0.9),
            $this->food('Huile', 900, 0, 0, 100),
        ];
        $goal = new MacroGoal(820, 42, 105, 25);

        $first = self::describe(new MealBuilder()->propose($goal, $foods));

        self::assertSame($first, self::describe(new MealBuilder()->propose($goal, $foods)));
        self::assertSame($first, self::describe(new MealBuilder()->propose($goal, array_reverse($foods))), 'the order of candidates of different roles does not matter');
    }

    public function testNothingIsProposedWhenTheGoalIsNearlyReached(): void
    {
        self::assertSame([], new MealBuilder()->propose(new MacroGoal(80, 5, 10, 2), [$this->food('Riz', 146, 2.9, 31.6, 0.5)]));
    }

    public function testAProposalNeverGoesFarBeyondTheCaloriesLeft(): void
    {
        // The smallest sensible portions (50 g of chicken, 40 g of puffed rice: 222 kcal) exceed 150 kcal + 10 %.
        $foods = [$this->food('Poulet', 141, 30.1, 0, 2), $this->food('Riz soufflé', 380, 7, 84, 1)];

        self::assertSame([], new MealBuilder()->propose(new MacroGoal(150, 10, 25, 0), $foods));
        self::assertNotEmpty(new MealBuilder()->propose(new MacroGoal(400, 30, 50, 5), $foods));
    }

    public function testWithoutSuitableFoodsThereIsNoProposal(): void
    {
        self::assertSame([], new MealBuilder()->propose(new MacroGoal(800, 40, 100, 25), []));
        self::assertSame([], new MealBuilder()->propose(new MacroGoal(800, 40, 100, 25), [$this->food('Huile', 900, 0, 0, 100)]));
    }

    public function testAMealGoalIsBoundByWhatIsLeftOfTheDay(): void
    {
        $goal = new MacroGoal(900, 45, 120, 26)->within(new MacroGoal(400, 60, 20, 30));

        self::assertSame([400.0, 45.0, 20.0, 26.0], [$goal->energyKcal, $goal->protein, $goal->carbohydrate, $goal->fat]);
    }

    public function testTheGoalIsWhatIsLeftNeverNegative(): void
    {
        $goal = MacroGoal::remainingOf(new NutrientAmounts(3000, 150, 400, 80), new NutrientAmounts(2200, 160, 300, 60));

        self::assertSame(800.0, $goal->energyKcal);
        self::assertSame(0.0, $goal->protein, 'protein already covered');
        self::assertSame(100.0, $goal->carbohydrate);
        self::assertSame(20.0, $goal->fat);
    }

    /**
     * @param list<MealProposal> $proposals
     *
     * @return list<string> the main food of each
     */
    private static function mains(array $proposals): array
    {
        return array_map(static fn (MealProposal $proposal): string => $proposal->portions[0]->food->name(), $proposals);
    }

    /**
     * @param list<MealProposal> $proposals
     *
     * @return list<list<string>>
     */
    private static function describe(array $proposals): array
    {
        return array_map(
            static fn (MealProposal $proposal): array => array_map(static fn (ProposedPortion $portion): string => $portion->food->name().' '.$portion->grams, $proposal->portions),
            $proposals,
        );
    }

    private function food(string $name, float $kcal, float $protein, float $carbohydrate, float $fat): Food
    {
        return Food::fromCatalogue(new FoodId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), FoodSource::Ciqual, (string) $this->ids, $name, null, new NutritionFacts($kcal, $protein, $carbohydrate, $fat));
    }
}
