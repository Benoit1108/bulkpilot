<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Coach\Application\Analysis\CoachView;
use App\Shared\Presentation\Api\OpenApi\Problems;

/**
 * The Coach (spec §62–70): at most three recommendations, the week's adherence, the last weekly
 * report. It never changes anything by itself.
 */
#[ApiResource(
    shortName: 'Coach',
    operations: [
        new Get(
            uriTemplate: '/coach',
            description: 'What the Coach offers now, as of its last analysis.',
            provider: CoachProvider::class,
            parameters: ['today' => new QueryParameter(description: 'The device\'s today (YYYY-MM-DD).', schema: ['type' => 'string', 'format' => 'date'])],
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY)]),
        ),
        new Post(
            uriTemplate: '/coach/analysis',
            status: 200,
            description: 'Runs the analysis if it has not run today (or again when asked), then answers the Coach.',
            input: AnalysisInput::class,
            read: false,
            openapi: new Operation(responses: ['400' => new Response(Problems::INVALID_QUERY), '409' => new Response(Problems::CONCURRENT), '429' => new Response(Problems::TOO_MANY)]),
            processor: CoachProcessor::class,
        ),
    ],
)]
final readonly class Coach
{
    /**
     * @param list<Recommendation> $recommendations
     */
    public function __construct(
        #[ApiProperty(identifier: true, description: 'The day it is for (the user\'s today).', required: true, schema: ['type' => 'string', 'format' => 'date'])]
        public string $today,
        #[ApiProperty(description: 'The last day the analysis ran; null before the first one.', required: true, schema: ['type' => ['string', 'null'], 'format' => 'date'])]
        public ?string $analysedOn,
        #[ApiProperty(required: true, schema: ['type' => 'string', 'enum' => ['direct', 'encouraging', 'neutral']])]
        public string $personality,
        #[ApiProperty(description: 'Shown now, the most important first (postponed ones aside); 3 at most.', required: true, readableLink: true, genId: false)]
        public array $recommendations,
        #[ApiProperty(description: 'Over the 7 days before the last analysis (its day aside); null before the first one.', required: true, readableLink: true, genId: false)]
        public ?AdherenceOutput $adherence,
        #[ApiProperty(description: 'The last weekly report, if any.', required: true, readableLink: true, genId: false)]
        public ?WeeklyReport $report,
    ) {
    }

    public static function of(\DateTimeImmutable $today, CoachView $view): self
    {
        return new self(
            $today->format('Y-m-d'),
            $view->settings->lastAnalysedOn?->format('Y-m-d'),
            $view->settings->personality->value,
            array_map(Recommendation::of(...), $view->recommendations),
            null === $view->settings->adherence ? null : AdherenceOutput::of($view->settings->adherence),
            null === $view->report ? null : WeeklyReport::of($view->report),
        );
    }
}
