<?php

declare(strict_types=1);

namespace App\Audit\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Audit\Application\Activity\ActivityView;
use App\Audit\Application\Activity\ListActivityHandler;
use App\Audit\Domain\Action;
use App\Audit\Domain\Source;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * « Activité du compte » (spec §10, §105): what happened to the account and who did it, to answer
 * "why did this value change?".
 */
#[ApiResource(
    shortName: 'Activity',
    operations: [
        new GetCollection(
            uriTemplate: '/me/activity',
            description: 'The account\'s important actions, the latest first (kept `AUDIT_RETENTION_DAYS` days).',
            paginationEnabled: false,
            provider: ActivityProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'At most '.ListActivityHandler::MAX.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => ListActivityHandler::MAX, 'default' => 20]),
                'before' => new QueryParameter(description: 'Only those before this time (the occurredAt of the last one received, for the next page)…', schema: ['type' => 'string', 'format' => 'date-time']),
                'beforeId' => new QueryParameter(description: '…and its id (several can share their time).', schema: ['type' => 'string', 'format' => 'uuid']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class Activity
{
    /**
     * @param array<string, string|int|float|bool|null> $details
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(description: 'Stable, `context.what` (e.g. `nutrition.target_set`).', required: true, schema: ['type' => 'string', 'enum' => Action::VALUES])]
        public string $action,
        #[ApiProperty(description: 'What changed (never a secret).', required: true, schema: ['type' => 'object', 'additionalProperties' => ['type' => ['string', 'number', 'boolean', 'null']]])]
        public array $details,
        #[ApiProperty(description: 'Who did it.', required: true, schema: ['type' => 'string', 'enum' => Source::ALL])]
        public string $source,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public static function of(ActivityView $view): self
    {
        return new self($view->id, $view->action, $view->details, $view->source, $view->occurredAt);
    }
}
