<?php

declare(strict_types=1);

namespace App\Coach\Application\Audit;

use App\Coach\Domain\Recommendation\RecommendationMade;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Which provider phrased what (spec §122); the figures it was given are kept with the recommendation.
 */
final readonly class LogRecommendationMade implements DomainEventSubscriber
{
    public function __construct(private LoggerInterface $auditLogger)
    {
    }

    public function __invoke(RecommendationMade $event): void
    {
        $this->auditLogger->info('coach.recommendation_phrased', [
            'user_id' => $event->userId->value,
            'recommendation_id' => $event->recommendationId->value,
            'kind' => $event->kind->value,
            'provider' => $event->provider,
            'occurred_on' => $event->occurredOn()->format(\DATE_ATOM),
        ]);
    }
}
