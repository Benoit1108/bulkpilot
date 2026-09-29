<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Domain\Weight\DayWeight;

final readonly class DayWeightOutput
{
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(required: true)]
        public float $weightKg,
    ) {
    }

    public static function of(?DayWeight $weight): ?self
    {
        return null === $weight ? null : new self($weight->day->format('Y-m-d'), $weight->kg);
    }
}
