<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Audit;

use App\Nutrition\Domain\Target\NutritionTargetSet;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Every target change is traced with its origin (spec §10: "2200 → 2350 kcal, source : coach").
 * The user-facing history is the target list itself; this log is the operator's trail.
 */
final readonly class LogTargetChange implements DomainEventSubscriber
{
    public function __construct(private LoggerInterface $auditLogger)
    {
    }

    public function __invoke(NutritionTargetSet $event): void
    {
        $targets = $event->targets;
        $this->auditLogger->info('nutrition.target_set', [
            'user_id' => $event->userId->value,
            'target_id' => $event->targetId->value,
            'effective_from' => $event->effectiveFrom->format('Y-m-d'),
            'previous_kcal' => $event->previous?->energyKcal,
            'kcal' => $targets->energyKcal,
            'protein_g' => $targets->proteinGrams,
            'carbohydrate_g' => $targets->carbohydrateGrams,
            'fat_g' => $targets->fatGrams,
            'source' => $event->source->value,
            'occurred_on' => $event->occurredOn()->format(\DATE_ATOM),
        ]);
    }
}
