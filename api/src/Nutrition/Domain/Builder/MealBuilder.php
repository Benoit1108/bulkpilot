<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Builder;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * Proposes meals that fill what is left of a target (spec §28), deterministically.
 *
 * Candidate foods are grouped by role (protein, carbohydrate, fat, side). For each meal pattern
 * (protein + carbohydrate, + fat, + side…) and each combination of candidates, the quantities are
 * fitted by bounded least squares on protein, carbohydrate and fat expressed in kcal (so that
 * 1 g of fat weighs as much as the 9 kcal it brings), then rounded to kitchen-scale grams. The best
 * few proposals are kept, each with a different main food so that they are real alternatives.
 */
final class MealBuilder
{
    public const int MAX_PROPOSALS = 3;
    /** Candidates kept per role, in the order given (the user's own habits come first). */
    public const int CANDIDATES_PER_ROLE = 6;
    /** A goal smaller than this is "nearly reached": no meal to build. */
    public const float MIN_GOAL_KCAL = 100.0;
    /** A proposal may exceed the calories left by this much, not more. */
    public const float MAX_OVERSHOOT = 1.1;

    private const int ITERATIONS = 60;

    /** @var list<list<FoodRole>> */
    private const array PATTERNS = [
        [FoodRole::Protein, FoodRole::Carbohydrate],
        [FoodRole::Protein, FoodRole::Carbohydrate, FoodRole::Fat],
        [FoodRole::Protein, FoodRole::Carbohydrate, FoodRole::Side],
        [FoodRole::Protein, FoodRole::Carbohydrate, FoodRole::Fat, FoodRole::Side],
        [FoodRole::Carbohydrate, FoodRole::Side],
        [FoodRole::Protein, FoodRole::Side],
    ];

    /**
     * @param list<Food> $candidates without duplicates, preferred first
     *
     * @return list<MealProposal> best first
     */
    public function propose(MacroGoal $goal, array $candidates): array
    {
        if ($goal->energyKcal < self::MIN_GOAL_KCAL) {
            return [];
        }

        $byRole = self::byRole($candidates);
        $proposals = [];
        foreach (self::PATTERNS as $pattern) {
            foreach (self::combinations($pattern, $byRole) as $combination) {
                $proposal = self::fit($goal, $combination);
                if (null !== $proposal) {
                    $proposals[] = $proposal;
                }
            }
        }
        usort($proposals, static fn (MealProposal $a, MealProposal $b): int => $a->gap <=> $b->gap);

        return self::varied($proposals);
    }

    /**
     * @param list<Food> $candidates
     *
     * @return array<value-of<FoodRole>, list<Food>>
     */
    private static function byRole(array $candidates): array
    {
        $byRole = [];
        foreach ($candidates as $food) {
            $role = FoodRole::of($food->facts());
            if (null !== $role && \count($byRole[$role->value] ?? []) < self::CANDIDATES_PER_ROLE) {
                $byRole[$role->value][] = $food;
            }
        }

        return $byRole;
    }

    /**
     * @param list<FoodRole>                        $pattern
     * @param array<value-of<FoodRole>, list<Food>> $byRole
     *
     * @return \Generator<list<array{Food, FoodRole}>>
     */
    private static function combinations(array $pattern, array $byRole, int $index = 0): \Generator
    {
        if ($index === \count($pattern)) {
            yield [];

            return;
        }
        $role = $pattern[$index];
        foreach ($byRole[$role->value] ?? [] as $food) {
            foreach (self::combinations($pattern, $byRole, $index + 1) as $rest) {
                yield [[$food, $role], ...$rest];
            }
        }
    }

    /**
     * Coordinate descent on the grams, each kept within its role's range; then rounding.
     *
     * @param list<array{Food, FoodRole}> $combination
     */
    private static function fit(MacroGoal $goal, array $combination): ?MealProposal
    {
        $factors = [NutrientAmounts::KCAL_PER_GRAM_PROTEIN, NutrientAmounts::KCAL_PER_GRAM_CARBOHYDRATE, NutrientAmounts::KCAL_PER_GRAM_FAT];
        $target = [$factors[0] * $goal->protein, $factors[1] * $goal->carbohydrate, $factors[2] * $goal->fat];
        $perGram = [];
        $grams = [];
        foreach ($combination as $i => [$food, $role]) {
            $facts = $food->facts();
            $perGram[$i] = [$factors[0] * $facts->protein / 100, $factors[1] * $facts->carbohydrate / 100, $factors[2] * $facts->fat / 100];
            $grams[$i] = $role->gramRange()[0];
        }

        for ($iteration = 0; $iteration < self::ITERATIONS; ++$iteration) {
            foreach ($combination as $i => [, $role]) {
                $numerator = 0.0;
                $denominator = 0.0;
                for ($m = 0; $m < 3; ++$m) {
                    $others = 0.0;
                    foreach ($grams as $j => $amount) {
                        if ($j !== $i) {
                            $others += $perGram[$j][$m] * $amount;
                        }
                    }
                    $numerator += $perGram[$i][$m] * ($target[$m] - $others);
                    $denominator += $perGram[$i][$m] ** 2;
                }
                [$min, $max] = $role->gramRange();
                $grams[$i] = $denominator > 0 ? max($min, min($max, $numerator / $denominator)) : $min;
            }
        }

        $portions = [];
        foreach ($combination as $i => [$food, $role]) {
            $step = $role->rounding();
            $portions[] = new ProposedPortion($food, $role, max($role->gramRange()[0], round($grams[$i] / $step) * $step));
        }
        $proposal = MealProposal::for($goal, $portions);

        return $proposal->totals->energyKcal > $goal->energyKcal * self::MAX_OVERSHOOT ? null : $proposal;
    }

    /**
     * The best proposals whose first (main) food differs.
     *
     * @param list<MealProposal> $sorted
     *
     * @return list<MealProposal>
     */
    private static function varied(array $sorted): array
    {
        $kept = [];
        $mains = [];
        foreach ($sorted as $proposal) {
            $main = $proposal->portions[0]->food->id()->value;
            if (!isset($mains[$main])) {
                $mains[$main] = true;
                $kept[] = $proposal;
            }
            if (self::MAX_PROPOSALS === \count($kept)) {
                break;
            }
        }

        return $kept;
    }
}
