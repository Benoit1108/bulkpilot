<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Goal\NextStep;

final readonly class NextStepOutput
{
    public function __construct(
        #[ApiProperty(required: true)]
        public float $weightKg,
        #[ApiProperty(description: 'The final target (not a milestone).', required: true)]
        public bool $target,
        #[ApiProperty(description: 'Kilograms left, signed the way the weight goes.', required: true)]
        public float $remainingKg,
        #[ApiProperty(required: true)]
        public EstimateOutput $estimate,
    ) {
    }

    public static function of(NextStep $next): self
    {
        return new self($next->step->kg, $next->step->target, $next->remainingKg, EstimateOutput::of($next->estimate));
    }
}
