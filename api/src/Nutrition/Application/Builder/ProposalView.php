<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Builder;

use App\Nutrition\Domain\Builder\MealProposal;
use App\Nutrition\Domain\Food\NutrientAmounts;

final readonly class ProposalView
{
    /**
     * @param list<ProposalItemView> $items
     */
    public function __construct(public array $items, public NutrientAmounts $totals, public float $gapKcal)
    {
    }

    public static function of(MealProposal $proposal): self
    {
        $items = [];
        foreach ($proposal->portions as $portion) {
            $items[] = new ProposalItemView($portion->food->id()->value, $portion->food->name(), $portion->role, $portion->grams, $portion->amounts());
        }

        return new self($items, $proposal->totals, $proposal->gap);
    }
}
