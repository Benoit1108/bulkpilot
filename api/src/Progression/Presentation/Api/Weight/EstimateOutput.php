<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Goal\Estimate;
use App\Progression\Domain\Goal\EstimateStatus;

/**
 * Weeks left at the current speed: a statistic, never a promise (spec §53).
 */
final readonly class EstimateOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['estimated', 'reached', 'not_enough_weigh_ins', 'not_moving', 'moving_away']])]
        public string $status,
        #[ApiProperty(description: 'Weeks left, rounded up (when estimated).', required: true)]
        public ?int $weeks,
    ) {
    }

    public static function of(Estimate $estimate): self
    {
        return new self($estimate->status->value, EstimateStatus::Estimated === $estimate->status ? $estimate->weeks : null);
    }
}
