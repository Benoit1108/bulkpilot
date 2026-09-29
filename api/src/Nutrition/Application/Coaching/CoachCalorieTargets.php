<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Coaching;

use App\Nutrition\Domain\Exception\InconsistentTarget;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Application\Coaching\CalorieTargets;
use App\Shared\Application\Coaching\TargetChanged;
use App\Shared\Application\Coaching\TargetUnreachable;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use Psr\Clock\ClockInterface;

/**
 * An accepted Coach proposal becomes a new target (ADR-0028): protein and fat kept, the
 * carbohydrates making up the new calories, recorded as coming from the Coach (the audit trail
 * says so). The estimate of the previous target is kept: the weight change aimed at stays known.
 *
 * Runs inside the Coach's command: its transaction, no dispatch of its own.
 */
final readonly class CoachCalorieTargets implements CalorieTargets
{
    public function __construct(
        private NutritionTargetRepository $targets,
        private EventBus $eventBus,
        private UuidGenerator $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function adjust(UserId $userId, int $fromKcal, int $byKcal, \DateTimeImmutable $effectiveFrom): void
    {
        $start = $effectiveFrom->setTime(0, 0);
        $current = $this->targets->inForceOn($userId, $start);
        if (null === $current || $current->targets()->energyKcal !== $fromKcal) {
            throw TargetChanged::since($fromKcal);
        }
        try {
            $macros = $current->targets()->withEnergy($fromKcal + $byKcal);
        } catch (InconsistentTarget $refused) {
            throw TargetUnreachable::because($refused->getMessage());
        }

        $target = NutritionTarget::set(
            new NutritionTargetId($this->uuids->generate()),
            $userId,
            $start,
            $macros,
            TargetSource::Coach,
            $current->estimate(),
            $current,
            $this->clock->now(),
        );
        $this->targets->save($target);
        $this->eventBus->publish(...$target->pullDomainEvents());
    }
}
