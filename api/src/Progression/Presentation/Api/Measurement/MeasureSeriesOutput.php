<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\ApiProperty;
use App\Progression\Application\Measurement\MeasureSeries;
use App\Progression\Domain\Measurement\DayLength;

final readonly class MeasureSeriesOutput
{
    /**
     * @param array{day: string, cm: float}|null  $latest
     * @param list<array{day: string, cm: float}> $points
     */
    public function __construct(
        #[ApiProperty(required: true)]
        public string $key,
        #[ApiProperty(description: 'Null for a body part (the app names it).', required: true)]
        public ?string $name,
        #[ApiProperty(required: true)]
        public bool $custom,
        #[ApiProperty(description: 'An archived measure of one\'s own is listed while it has values.', required: true)]
        public bool $archived,
        #[ApiProperty(description: 'The latest value ever, even before the period.', required: true, schema: ['type' => ['object', 'null'], 'properties' => ['day' => ['type' => 'string', 'format' => 'date'], 'cm' => ['type' => 'number']], 'required' => ['day', 'cm']])]
        public ?array $latest,
        #[ApiProperty(description: 'Latest minus the first value of the period.', required: true)]
        public ?float $changeCm,
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['day' => ['type' => 'string', 'format' => 'date'], 'cm' => ['type' => 'number']], 'required' => ['day', 'cm']]])]
        public array $points,
    ) {
    }

    public static function of(MeasureSeries $series): self
    {
        return new self(
            $series->measure->key,
            $series->measure->name,
            null !== $series->measure->name,
            $series->measure->archived,
            null === $series->latest ? null : self::point($series->latest),
            $series->changeCm,
            array_map(self::point(...), $series->points),
        );
    }

    /**
     * @return array{day: string, cm: float}
     */
    private static function point(DayLength $point): array
    {
        return ['day' => $point->day->format('Y-m-d'), 'cm' => $point->cm];
    }
}
