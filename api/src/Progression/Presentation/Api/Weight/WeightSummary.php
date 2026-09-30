<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Progression\Application\Weight\WeightSummaryView;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The weight page (spec §51–55): the latest weigh-in, the 7 and 30-day averages and the speed
 * ending today, the change since the first weigh-in, the goal, and the chart of a period.
 */
#[ApiResource(
    shortName: 'WeightSummary',
    operations: [
        new Get(
            uriTemplate: '/progression/weight',
            description: 'Where the weight stands and its chart over a period.',
            provider: WeightSummaryProvider::class,
            parameters: [
                'period' => new QueryParameter(description: 'The span of the chart.', schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all'], 'default' => '3m']),
                'today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD): the averages end on it.', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class WeightSummary
{
    /**
     * @param list<TrendPointOutput>  $points
     * @param list<WeekAverageOutput> $weeks
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all']])]
        public string $period,
        #[ApiProperty(required: true)]
        public ?DayWeightOutput $latest,
        #[ApiProperty(description: 'The first weigh-in ever: where the user started from.', required: true)]
        public ?DayWeightOutput $first,
        #[ApiProperty(description: 'Mean of the weigh-ins of the last 7 days (today included).', required: true)]
        public ?float $average7Kg,
        #[ApiProperty(description: 'Mean of the weigh-ins of the last 30 days.', required: true)]
        public ?float $average30Kg,
        #[ApiProperty(description: 'Speed over the last 4 weeks (least squares); null with too few weigh-ins.', required: true)]
        public ?float $kgPerWeek,
        #[ApiProperty(required: true, schema: ['type' => ['string', 'null'], 'enum' => ['gaining', 'losing', 'stable', null]])]
        public ?string $direction,
        #[ApiProperty(description: 'Latest minus first weigh-in.', required: true)]
        public ?float $sinceStartKg,
        #[ApiProperty(description: 'The last weigh-in of the period minus its first (null with fewer than two): the change over 7 days, 30 days…', required: true)]
        public ?float $periodChangeKg,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public ?WeightGoal $goal,
        #[ApiProperty(description: 'Each weigh-in of the period, with the averages ending that day.', required: true)]
        public array $points,
        #[ApiProperty(description: 'Mean weight of each week of the period (Monday first).', required: true)]
        public array $weeks,
    ) {
    }

    public static function of(Period $period, WeightSummaryView $view): self
    {
        return new self(
            $period->value,
            DayWeightOutput::of($view->latest),
            DayWeightOutput::of($view->first),
            $view->average7,
            $view->average30,
            $view->kgPerWeek,
            $view->direction?->value,
            $view->sinceStartKg,
            $view->periodChangeKg,
            null === $view->goal ? null : WeightGoal::of($view->goal),
            array_map(TrendPointOutput::of(...), $view->points),
            array_map(WeekAverageOutput::of(...), $view->weeks),
        );
    }
}
