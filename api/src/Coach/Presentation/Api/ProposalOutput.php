<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Coach\Domain\Rule\CalorieProposal;

/**
 * The calorie change accepting would make: a new target from tomorrow (spec §66).
 */
final readonly class ProposalOutput
{
    public function __construct(
        #[ApiProperty(description: 'The target it was made against.', required: true)]
        public int $fromKcal,
        #[ApiProperty(description: 'Signed change.', required: true)]
        public int $byKcal,
        #[ApiProperty(required: true)]
        public int $toKcal,
        #[ApiProperty(description: 'About how many grams of carbohydrates a day it changes (protein and fat are kept; the exact figure is set when accepted).', required: true)]
        public int $carbohydrateChangeGrams,
    ) {
    }

    public static function of(CalorieProposal $proposal): self
    {
        return new self($proposal->fromKcal, $proposal->byKcal, $proposal->toKcal(), (int) round($proposal->byKcal / 4));
    }
}
