<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Application\Weight\WeighInView;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The weight of one of the user's days: one a day, identified by the day.
 */
#[ApiResource(
    shortName: 'WeighIn',
    operations: [
        new GetCollection(
            uriTemplate: '/progression/weigh-ins',
            description: 'The weigh-ins, the most recent first.',
            paginationEnabled: false,
            provider: WeighInProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'How many (at most 100).', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30]),
                'before' => new QueryParameter(description: 'Only the days before this one (the next page).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
        new Get(uriTemplate: '/progression/weigh-ins/{day}', requirements: ['day' => CalendarDays::PATTERN], description: 'The weigh-in of a day.', provider: WeighInProvider::class),
        new Put(
            uriTemplate: '/progression/weigh-ins/{day}',
            requirements: ['day' => CalendarDays::PATTERN],
            description: 'Records the weight of a day, or replaces it (one a day; sending it again changes nothing).',
            input: WeighInInput::class,
            read: false,
            openapi: new Operation(responses: ['409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: WeighInProcessor::class,
        ),
        new Delete(
            uriTemplate: '/progression/weigh-ins/{day}',
            requirements: ['day' => CalendarDays::PATTERN],
            description: 'Removes the weigh-in of a day (removing it again changes nothing).',
            read: false,
            openapi: new Operation(responses: ['429' => new Response(Problems::TOO_MANY)]),
            processor: WeighInProcessor::class,
        ),
    ],
)]
final readonly class WeighIn
{
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $day,
        #[ApiProperty(required: true)]
        public float $weightKg,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $recordedAt,
    ) {
    }

    public static function fromView(WeighInView $view): self
    {
        return new self($view->day->format('Y-m-d'), $view->weightKg, $view->recordedAt);
    }
}
