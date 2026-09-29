<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\Weight;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class RecordWeighInHandler implements CommandHandler
{
    public function __construct(private WeighInRepository $weighIns, private EventBus $eventBus, private ClockInterface $clock)
    {
    }

    public function __invoke(RecordWeighIn $command): void
    {
        $weight = new Weight($command->weightKg);
        $now = $this->clock->now();
        $weighIn = $this->weighIns->on($command->userId, $command->day);
        if (null === $weighIn) {
            $weighIn = WeighIn::record($command->weighInId, $command->userId, $command->day, $weight, $now);
        } else {
            $weighIn->correct($weight, $now);
        }
        $this->weighIns->save($weighIn);
        $this->eventBus->publish(...$weighIn->pullDomainEvents());
    }
}
