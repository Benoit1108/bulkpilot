<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\Health;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\System\Application\Health\HealthReport;

#[ApiResource(
    shortName: 'Health',
    operations: [
        new Get(
            uriTemplate: '/health',
            description: 'Readiness of the API and its dependencies. Answers 503 with the same body when degraded.',
            openapi: new Operation(responses: ['503' => new Response(description: 'At least one dependency is down')]),
            provider: HealthProvider::class,
        ),
    ],
)]
final readonly class Health
{
    /**
     * @param 'ok'|'degraded'                      $status
     * @param array<non-empty-string, 'up'|'down'> $checks
     */
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['ok', 'degraded']])]
        public string $status,
        #[ApiProperty(required: true, schema: ['type' => 'object', 'additionalProperties' => ['type' => 'string', 'enum' => ['up', 'down']]])]
        public array $checks,
    ) {
    }

    public static function fromReport(HealthReport $report): self
    {
        return new self(
            $report->isHealthy() ? 'ok' : 'degraded',
            array_map(static fn (bool $up): string => $up ? 'up' : 'down', $report->checks),
        );
    }
}
