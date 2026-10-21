<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Presentation\Api\OpenApi\Problems;
use App\Training\Application\Progress\TrainingWeekView;

/**
 * The week under way and the one before it, whatever period the other figures cover: the working
 * sets by muscle so far, and what the last full week lifted.
 */
#[ApiResource(
    shortName: 'TrainingWeek',
    operations: [
        new Get(
            uriTemplate: '/training/week',
            description: 'Working sets of the week under way by the main muscle of their exercise, and the last full week\'s sessions and tonnage (weeks start on Monday).',
            provider: TrainingWeekProvider::class,
            parameters: [
                'today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class TrainingWeek
{
    /**
     * @param list<array{muscle: string, workingSets: int}>                          $muscles
     * @param array{week: string, sessions: int, workingSets: int, tonnageKg: float} $lastWeek
     */
    public function __construct(
        #[ApiProperty(identifier: true, description: 'The Monday of the week under way.', required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $week,
        #[ApiProperty(description: 'Working sets so far by the main muscle of their exercise, the most worked first.', required: true, schema: ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'muscle' => ['type' => 'string', 'enum' => ExerciseChoices::MUSCLES], 'workingSets' => ['type' => 'integer'],
        ], 'required' => ['muscle', 'workingSets']]])]
        public array $muscles,
        #[ApiProperty(description: 'The last full week (from its Monday).', required: true, schema: ['type' => 'object', 'properties' => [
            'week' => ['type' => 'string', 'format' => 'date'], 'sessions' => ['type' => 'integer'], 'workingSets' => ['type' => 'integer'], 'tonnageKg' => ['type' => 'number'],
        ], 'required' => ['week', 'sessions', 'workingSets', 'tonnageKg']])]
        public array $lastWeek,
    ) {
    }

    public static function of(TrainingWeekView $view): self
    {
        $last = $view->lastWeekTotals;

        return new self(
            $view->week->format('Y-m-d'),
            array_map(static fn (string $muscle, int $sets): array => ['muscle' => $muscle, 'workingSets' => $sets], array_keys($view->muscles), array_values($view->muscles)),
            ['week' => $view->lastWeek->format('Y-m-d'), 'sessions' => $last->sessions, 'workingSets' => $last->workingSets, 'tonnageKg' => $last->tonnageKg],
        );
    }
}
