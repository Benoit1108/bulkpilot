<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Application\Measurement\MeasurementDayView;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The measurements of one of the user's days: one set a day, identified by the day.
 */
#[ApiResource(
    shortName: 'Measurements',
    operations: [
        new Get(uriTemplate: '/progression/measurements/{day}', requirements: ['day' => CalendarDays::PATTERN], description: 'The measurements of a day.', provider: MeasurementsProvider::class),
        new Put(
            uriTemplate: '/progression/measurements/{day}',
            requirements: ['day' => CalendarDays::PATTERN],
            description: 'Records the measurements of a day, or replaces them (one set a day; a measure left out is not kept).',
            input: MeasurementsInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: MeasurementsProcessor::class,
        ),
        new Delete(
            uriTemplate: '/progression/measurements/{day}',
            requirements: ['day' => CalendarDays::PATTERN],
            description: 'Removes the measurements of a day (removing them again changes nothing).',
            read: false,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: MeasurementsProcessor::class,
        ),
    ],
)]
final readonly class Measurements
{
    /**
     * @param array<string, float> $centimetres
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(description: 'Centimetres by measure key.', required: true, schema: ['type' => 'object', 'additionalProperties' => ['type' => 'number']])]
        public array $centimetres,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $recordedAt,
    ) {
    }

    public static function of(MeasurementDayView $view): self
    {
        return new self($view->day->format('Y-m-d'), $view->centimetres, $view->recordedAt);
    }
}
