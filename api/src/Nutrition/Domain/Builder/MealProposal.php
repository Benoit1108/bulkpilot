<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Builder;

use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class MealProposal
{
    /**
     * @param list<ProposedPortion> $portions
     * @param float                 $gap      distance to the goal, in kcal
     */
    private function __construct(public array $portions, public NutrientAmounts $totals, public float $gap)
    {
    }

    /**
     * The gap weighs each macro error by its energy (4 / 4 / 9 kcal per gram), so that 1 g of fat
     * missing counts as much as the 9 kcal it would have brought.
     *
     * @param list<ProposedPortion> $portions
     */
    public static function for(MacroGoal $goal, array $portions): self
    {
        $totals = NutrientAmounts::sum(array_map(static fn (ProposedPortion $portion): NutrientAmounts => $portion->amounts(), $portions));
        $gap = sqrt(
            (NutrientAmounts::KCAL_PER_GRAM_PROTEIN * ($totals->protein - $goal->protein)) ** 2
            + (NutrientAmounts::KCAL_PER_GRAM_CARBOHYDRATE * ($totals->carbohydrate - $goal->carbohydrate)) ** 2
            + (NutrientAmounts::KCAL_PER_GRAM_FAT * ($totals->fat - $goal->fat)) ** 2,
        );

        return new self($portions, $totals, $gap);
    }
}
