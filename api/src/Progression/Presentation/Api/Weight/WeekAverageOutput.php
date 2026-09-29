<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Application\Weight\WeekAverage;

final readonly class WeekAverageOutput
{
    public function __construct(
        #[ApiProperty(description: 'The Monday of the week.', required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $week,
        #[ApiProperty(required: true)]
        public float $averageKg,
        #[ApiProperty(required: true)]
        public int $weighIns,
    ) {
    }

    public static function of(WeekAverage $week): self
    {
        return new self($week->week->format('Y-m-d'), $week->averageKg, $week->weighIns);
    }
}
