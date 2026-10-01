<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Audit;

use App\Nutrition\Domain\Target\NutritionTargetSet;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * Every target change is traced with its origin (spec §10: "2200 → 2350 kcal, source : coach"):
 * the Coach when the user accepted its proposal, the user otherwise (typed or from the estimate).
 */
final readonly class LogTargetChange implements DomainEventSubscriber
{
    public function __construct(private AuditTrail $audit)
    {
    }

    public function __invoke(NutritionTargetSet $event): void
    {
        $targets = $event->targets;
        $this->audit->record($event->userId, 'nutrition.target_set', [
            'previous_kcal' => $event->previous?->energyKcal,
            'kcal' => $targets->energyKcal,
            'effective_from' => $event->effectiveFrom->format('Y-m-d'),
            'origin' => $event->source->value,
        ], TargetSource::Coach === $event->source ? 'coach' : 'user', $event->occurredOn());
    }
}
