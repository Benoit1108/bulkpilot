<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Weight\TrendPoint;

final readonly class TrendPointOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(required: true)]
        public float $weightKg,
        #[ApiProperty(description: 'Mean of the weigh-ins of the 7 days ending that day.', required: true)]
        public float $average7Kg,
        #[ApiProperty(description: 'Mean of the weigh-ins of the 30 days ending that day.', required: true)]
        public float $average30Kg,
    ) {
    }

    public static function of(TrendPoint $point): self
    {
        return new self($point->day->format('Y-m-d'), $point->kg, $point->average7, $point->average30);
    }
}
