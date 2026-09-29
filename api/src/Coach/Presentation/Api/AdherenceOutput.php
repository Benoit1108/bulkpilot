<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Coach\Domain\Analysis\Adherence;

/**
 * How well the plan was kept (spec §58), explained part by part.
 */
final readonly class AdherenceOutput
{
    /**
     * @param list<AdherencePartOutput> $parts
     */
    public function __construct(
        #[ApiProperty(description: 'The mean of the parts, 0 to 100; null when nothing was recorded (no day logged, no weigh-in, no session).', required: true)]
        public ?int $score,
        #[ApiProperty(description: 'Calories: days within 10 % of the target. Protein: days at 90 % of it or more. Training: sessions of those planned. Weigh-ins: days weighed of 3.', required: true)]
        public array $parts,
    ) {
    }

    public static function of(Adherence $adherence): self
    {
        return new self($adherence->score(), array_map(AdherencePartOutput::of(...), $adherence->components));
    }
}
