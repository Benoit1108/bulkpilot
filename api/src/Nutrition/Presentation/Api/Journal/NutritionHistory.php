<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Nutrition\Application\Journal\NutritionHistoryView;
use App\Nutrition\Application\Journal\WeekIntake;
use App\Nutrition\Domain\Journal\DayTotals;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * Calories and proteins over a period (spec §55–56): each complete day logged, each week's
 * mean, the mean of the period, and the target in force today.
 */
#[ApiResource(
    shortName: 'NutritionHistory',
    operations: [
        new Get(
            uriTemplate: '/nutrition/history',
            description: 'Day and week totals of the days logged over a period (today excluded: it is under way).',
            provider: NutritionHistoryProvider::class,
            parameters: [
                'period' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all'], 'default' => '3m']),
                'today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class NutritionHistory
{
    /**
     * @param list<array{day: string, energyKcal: float, proteinGrams: float}>                           $days
     * @param list<array{week: string, averageEnergyKcal: float, averageProteinGrams: float, days: int}> $weeks
     * @param array{energyKcal: int, proteinGrams: int}|null                                             $target
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true, schema: ['type' => 'string', 'enum' => ['7d', '30d', '3m', '6m', '1y', 'all']])]
        public string $period,
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['day' => ['type' => 'string', 'format' => 'date'], 'energyKcal' => ['type' => 'number'], 'proteinGrams' => ['type' => 'number']], 'required' => ['day', 'energyKcal', 'proteinGrams']]])]
        public array $days,
        #[ApiProperty(description: 'Monday first; the mean of the days logged that week.', required: true, schema: ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['week' => ['type' => 'string', 'format' => 'date'], 'averageEnergyKcal' => ['type' => 'number'], 'averageProteinGrams' => ['type' => 'number'], 'days' => ['type' => 'integer']], 'required' => ['week', 'averageEnergyKcal', 'averageProteinGrams', 'days']]])]
        public array $weeks,
        #[ApiProperty(required: true)]
        public ?float $averageEnergyKcal,
        #[ApiProperty(required: true)]
        public ?float $averageProteinGrams,
        #[ApiProperty(description: 'The daily targets in force today.', required: true, schema: ['type' => ['object', 'null'], 'properties' => ['energyKcal' => ['type' => 'integer'], 'proteinGrams' => ['type' => 'integer']], 'required' => ['energyKcal', 'proteinGrams']])]
        public ?array $target,
    ) {
    }

    public static function of(Period $period, NutritionHistoryView $view): self
    {
        return new self(
            $period->value,
            array_map(static fn (DayTotals $day): array => ['day' => $day->day->format('Y-m-d'), 'energyKcal' => round($day->amounts->energyKcal, 1), 'proteinGrams' => round($day->amounts->protein, 1)], $view->days),
            array_map(static fn (WeekIntake $week): array => ['week' => $week->week->format('Y-m-d'), 'averageEnergyKcal' => $week->averageEnergyKcal, 'averageProteinGrams' => $week->averageProtein, 'days' => $week->days], $view->weeks),
            $view->averageEnergyKcal,
            $view->averageProtein,
            null === $view->target ? null : ['energyKcal' => $view->target->energyKcal, 'proteinGrams' => $view->target->proteinGrams],
        );
    }
}
