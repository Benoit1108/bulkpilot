<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Presentation\Api\OpenApi\Problems;
use App\Training\Application\Progress\TrainingAnalyticsView;
use App\Training\Application\Progress\TrainingTotals;

/**
 * The training of a period (spec §57): sessions, frequency, working sets, reps, tonnage and
 * records beaten, in all and week by week.
 */
#[ApiResource(
    shortName: 'TrainingAnalytics',
    operations: [
        new Get(
            uriTemplate: '/training/analytics',
            description: 'Totals of the finished workouts of a period, and of each of its weeks (Monday first).',
            provider: TrainingAnalyticsProvider::class,
            parameters: [
                'period' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all'], 'default' => '3m']),
                'today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class TrainingAnalytics
{
    private const array TOTALS = ['type' => 'object', 'properties' => [
        'sessions' => ['type' => 'integer'], 'workingSets' => ['type' => 'integer'], 'reps' => ['type' => 'integer'],
        'tonnageKg' => ['type' => 'number'], 'records' => ['type' => 'integer'],
    ], 'required' => ['sessions', 'workingSets', 'reps', 'tonnageKg', 'records']];

    /**
     * @param array{sessions: int, workingSets: int, reps: int, tonnageKg: float, records: int}                     $totals
     * @param list<array{week: string, sessions: int, workingSets: int, reps: int, tonnageKg: float, records: int}> $weeks
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all']])]
        public string $period,
        #[ApiProperty(required: true, schema: self::TOTALS)]
        public array $totals,
        #[ApiProperty(description: 'Every week of the period, those without a session included.', required: true, schema: ['type' => 'array', 'items' => ['allOf' => [self::TOTALS, ['type' => 'object', 'properties' => ['week' => ['type' => 'string', 'format' => 'date']], 'required' => ['week']]]]])]
        public array $weeks,
        #[ApiProperty(description: 'Sessions a week on average over the period.', required: true)]
        public ?float $sessionsPerWeek,
    ) {
    }

    public static function of(Period $period, TrainingAnalyticsView $view): self
    {
        return new self(
            $period->value,
            self::totals($view->totals),
            array_map(static fn (string $week, TrainingTotals $totals): array => ['week' => $week] + self::totals($totals), array_keys($view->weeks), array_values($view->weeks)),
            $view->sessionsPerWeek,
        );
    }

    /**
     * @return array{sessions: int, workingSets: int, reps: int, tonnageKg: float, records: int}
     */
    private static function totals(TrainingTotals $totals): array
    {
        return ['sessions' => $totals->sessions, 'workingSets' => $totals->workingSets, 'reps' => $totals->reps, 'tonnageKg' => $totals->tonnageKg, 'records' => $totals->records];
    }
}
