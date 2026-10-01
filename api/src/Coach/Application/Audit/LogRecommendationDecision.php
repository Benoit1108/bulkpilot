<?php

declare(strict_types=1);

namespace App\Coach\Application\Audit;

use App\Coach\Domain\Recommendation\RecommendationDecided;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * Every decision on a recommendation is traced (spec §10: "approved_by: user"). The target it
 * changed, if any, is traced by Nutrition with its source.
 */
final readonly class LogRecommendationDecision implements DomainEventSubscriber
{
    public function __construct(private AuditTrail $audit)
    {
    }

    public function __invoke(RecommendationDecided $event): void
    {
        $this->audit->record($event->userId, 'coach.recommendation_decided', [
            'kind' => $event->kind->value,
            'status' => $event->status->value,
        ], 'user', $event->occurredOn());
    }
}
