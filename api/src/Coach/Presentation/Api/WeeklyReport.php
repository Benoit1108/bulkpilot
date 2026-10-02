<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Coach\Application\Report\ListWeeklyReports;
use App\Coach\Application\Report\WeeklyReportView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The report of a finished week (spec §59), as drawn when the week ended.
 */
#[ApiResource(
    shortName: 'CoachWeeklyReport',
    operations: [
        new GetCollection(
            uriTemplate: '/coach/reports',
            description: 'The weekly reports, the latest first, a page at a time.',
            paginationEnabled: false,
            provider: WeeklyReportProvider::class,
            parameters: [
                'limit' => new QueryParameter(description: 'At most '.ListWeeklyReports::MAX.'.', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => ListWeeklyReports::MAX, 'default' => ListWeeklyReports::PAGE]),
                'before' => new QueryParameter(description: 'Only the weeks before this one (the weekStart of the last report received, for the next page).', schema: ['type' => 'string', 'format' => 'date']),
            ],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
    ],
)]
final readonly class WeeklyReport
{
    public function __construct(
        #[ApiProperty(identifier: true, description: 'The Monday of the week.', required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $weekStart,
        #[ApiProperty(description: 'The 7-day mean weight at the end of the week before.', required: true)]
        public ?float $startKg,
        #[ApiProperty(description: 'The 7-day mean weight at the end of the week.', required: true)]
        public ?float $endKg,
        #[ApiProperty(required: true)]
        public ?float $changeKg,
        #[ApiProperty(description: 'Speed over the 4 weeks ending with it.', required: true)]
        public ?float $kgPerWeek,
        #[ApiProperty(description: 'The pace aimed at.', required: true)]
        public ?float $paceKgPerWeek,
        #[ApiProperty(description: 'Mean of the days logged.', required: true)]
        public ?float $averageKcal,
        #[ApiProperty(required: true)]
        public ?float $averageProtein,
        #[ApiProperty(required: true)]
        public int $loggedDays,
        #[ApiProperty(required: true)]
        public ?int $targetKcal,
        #[ApiProperty(required: true)]
        public ?int $targetProtein,
        #[ApiProperty(required: true)]
        public int $sessions,
        #[ApiProperty(required: true)]
        public ?int $plannedSessions,
        #[ApiProperty(required: true)]
        public int $records,
        #[ApiProperty(required: true, readableLink: true, genId: false)]
        public AdherenceOutput $adherence,
        #[ApiProperty(description: 'The weight against the pace aimed at.', required: true, schema: ['type' => 'string', 'enum' => ['on_track', 'below', 'above', 'unknown']])]
        public string $verdict,
        #[ApiProperty(description: 'What the Coach asked for most, in the imperative; null to carry on with the plan.', required: true)]
        public ?string $action,
    ) {
    }

    public static function of(WeeklyReportView $report): self
    {
        $figures = $report->figures;

        return new self(
            $report->weekStart->format('Y-m-d'),
            $figures->startKg,
            $figures->endKg,
            $figures->changeKg(),
            $figures->kgPerWeek,
            $figures->paceKgPerWeek,
            $figures->averageKcal,
            $figures->averageProtein,
            $figures->loggedDays,
            $figures->targetKcal,
            $figures->targetProtein,
            $figures->sessions,
            $figures->plannedSessions,
            $figures->records,
            AdherenceOutput::of($report->adherence),
            $report->verdict->value,
            $report->action,
        );
    }
}
