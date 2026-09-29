<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Goal\StepProgress;

final readonly class GoalStepOutput
{
    public function __construct(
        #[ApiProperty(required: true)]
        public float $weightKg,
        #[ApiProperty(description: 'The final target (the others are milestones).', required: true)]
        public bool $target,
        #[ApiProperty(description: 'The first day a weigh-in reached it.', required: true, schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $reachedOn,
    ) {
    }

    public static function of(StepProgress $step): self
    {
        return new self($step->kg, $step->target, $step->reachedOn?->format('Y-m-d'));
    }
}
