<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Builder;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Builder\ProposalView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class Proposal
{
    /**
     * @param list<ProposalItem> $items
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public array $items,
        #[ApiProperty(required: true)]
        public Nutrients $totals,
        /** Distance to the goal in kcal: the smaller, the closer. */
        #[ApiProperty(required: true)]
        public int $gapKcal,
    ) {
    }

    public static function of(ProposalView $view): self
    {
        return new self(array_map(ProposalItem::of(...), $view->items), Nutrients::of($view->totals), (int) round($view->gapKcal));
    }
}
