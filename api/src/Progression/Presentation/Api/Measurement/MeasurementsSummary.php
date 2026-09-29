<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * Every measure over a period (spec §54–55).
 */
#[ApiResource(
    shortName: 'MeasurementsSummary',
    operations: [
        new Get(
            uriTemplate: '/progression/measurements',
            description: 'Each measure over a period: its values, its latest value, its change.',
            provider: MeasurementsSummaryProvider::class,
            parameters: [
                'period' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all'], 'default' => '3m']),
                'today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class MeasurementsSummary
{
    /**
     * @param list<MeasureSeriesOutput> $measures
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all']])]
        public string $period,
        #[ApiProperty(description: 'The body parts then the user\'s own measures.', required: true)]
        public array $measures,
    ) {
    }
}
