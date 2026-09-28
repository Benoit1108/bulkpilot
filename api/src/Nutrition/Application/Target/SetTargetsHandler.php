<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Nutrition\Domain\Target\TargetSource;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class SetTargetsHandler implements CommandHandler
{
    public function __construct(
        private NutritionTargetRepository $targets,
        private EnergyCalculator $calculator,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetTargets $command): void
    {
        $now = $this->clock->now();
        $effectiveFrom = $command->effectiveFrom->setTime(0, 0);

        $estimate = null === $command->estimate ? null : $this->calculator->estimate($command->estimate->inputs($now));
        $source = null !== $estimate && $estimate->targets->equals($command->targets) ? TargetSource::Estimate : TargetSource::Manual;

        $target = NutritionTarget::set(
            $command->targetId,
            $command->userId,
            $effectiveFrom,
            $command->targets,
            $source,
            $estimate,
            $this->targets->inForceOn($command->userId, $effectiveFrom),
            $now,
        );
        $this->targets->save($target);
        $this->eventBus->publish(...$target->pullDomainEvents());
    }
}
