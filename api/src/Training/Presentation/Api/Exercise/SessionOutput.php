<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\ApiProperty;

final readonly class SessionOutput
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $workoutId,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $date,
        #[ApiProperty(required: true)]
        public int $workingSets,
        #[ApiProperty(required: true)]
        public ?float $topLoadKg,
        #[ApiProperty(required: true)]
        public ?float $estimatedOneRepMax,
        #[ApiProperty(required: true)]
        public float $volumeKg,
        #[ApiProperty(required: true)]
        public ?int $bestReps,
        #[ApiProperty(required: true)]
        public ?int $bestSeconds,
    ) {
    }
}
