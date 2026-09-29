<?php

declare(strict_types=1);

namespace App\Coach\Application\Audit;

use App\Coach\Domain\Recommendation\RecommendationDecided;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Every decision on a recommendation is traced (spec §10: "approved_by: user"). The target it
 * changed, if any, is traced by Nutrition with its source.
 */
final readonly class LogRecommendationDecision implements DomainEventSubscriber
{
    public function __construct(private LoggerInterface $auditLogger)
    {
    }

    public function __invoke(RecommendationDecided $event): void
    {
        $this->auditLogger->info('coach.recommendation_decided', [
            'user_id' => $event->userId->value,
            'recommendation_id' => $event->recommendationId->value,
            'kind' => $event->kind->value,
            'status' => $event->status->value,
            'occurred_on' => $event->occurredOn()->format(\DATE_ATOM),
        ]);
    }
}
