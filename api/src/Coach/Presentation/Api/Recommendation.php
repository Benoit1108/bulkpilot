<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Coach\Application\Recommendation\ListRecommendationsHandler;
use App\Coach\Application\Recommendation\RecommendationView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * A recommendation of the Coach (spec §68–70), with its "why", the figures it was made on and
 * what the user decided.
 */
#[ApiResource(
    shortName: 'CoachRecommendation',
    operations: [
        new GetCollection(
            uriTemplate: '/coach/recommendations',
            description: 'The Coach\'s history, the latest first.',
            paginationEnabled: false,
            provider: RecommendationProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'At most '.ListRecommendationsHandler::MAX.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => ListRecommendationsHandler::MAX, 'default' => 20]),
                'before' => new QueryParameter(description: 'The next page: the createdAt of the last one received…', schema: ['type' => 'string', 'format' => 'date-time']),
                'beforeId' => new QueryParameter(description: '…and its id (those of one analysis share their time).', schema: ['type' => 'string', 'format' => 'uuid']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
        new Get(
            uriTemplate: '/coach/recommendations/{id}',
            requirements: ['id' => '[0-9a-f-]{36}'],
            provider: RecommendationProvider::class,
            openapi: new Operation(responses: ['404' => new Response(Problems::NOT_FOUND)]),
        ),
        new Post(
            uriTemplate: '/coach/recommendations/{id}/decision',
            requirements: ['id' => '[0-9a-f-]{36}'],
            status: 200,
            description: 'Accepts, rejects or postpones it. Accepting a calorie proposal sets a new target from tomorrow (source: coach).',
            input: DecisionInput::class,
            read: false,
            openapi: new Operation(responses: [
                '404' => new Response(Problems::NOT_FOUND),
                '409' => new Response('No longer offered (`coach.recommendation_closed`), the target changed since (`coach.target_changed`), or: '.Problems::CONCURRENT),
                '422' => new Response('The change would make an invalid target (`coach.target_unreachable`: calories out of bounds, or too few left for the protein and fat).'),
                '429' => new Response(Problems::TOO_MANY),
            ]),
            processor: RecommendationProcessor::class,
            name: 'coach_recommendation_decision',
        ),
    ],
)]
final readonly class Recommendation
{
    /**
     * @param list<string>         $why
     * @param array<string, mixed> $figures
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => [
            'calories.no_target', 'calories.no_pace', 'calories.increase', 'calories.decrease', 'calories.reach_target_first', 'calories.back_to_target',
            'protein.low', 'journal.incomplete', 'weigh_ins.few', 'training.missed_sessions', 'training.stall', 'training.records',
        ]])]
        public string $kind,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['priority', 'watch', 'info']])]
        public string $level,
        #[ApiProperty(required: true)]
        public string $title,
        #[ApiProperty(required: true)]
        public string $body,
        #[ApiProperty(description: 'The reasons, one per line (spec §69).', required: true)]
        public array $why,
        #[ApiProperty(description: 'What accepting changes; null for advice.', required: true)]
        public ?ProposalOutput $proposal,
        #[ApiProperty(description: 'The figures it was made on, computed by the server (spec §70).', required: true, schema: ['type' => 'object', 'additionalProperties' => true])]
        public array $figures,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['pending', 'accepted', 'rejected', 'postponed', 'expired']])]
        public string $status,
        #[ApiProperty(description: 'Who phrased it (spec §122).', required: true)]
        public string $provider,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $createdAt,
        #[ApiProperty(required: true)]
        public \DateTimeImmutable $expiresAt,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $postponedUntil,
        #[ApiProperty(required: true)]
        public ?\DateTimeImmutable $decidedAt,
    ) {
    }

    public static function of(RecommendationView $view): self
    {
        return new self(
            $view->id,
            $view->kind->value,
            $view->level->value,
            $view->title,
            $view->body,
            $view->why,
            null === $view->proposal ? null : ProposalOutput::of($view->proposal),
            $view->figures,
            $view->status->value,
            $view->provider,
            $view->createdAt,
            $view->expiresAt,
            $view->postponedUntil,
            $view->decidedAt,
        );
    }
}
