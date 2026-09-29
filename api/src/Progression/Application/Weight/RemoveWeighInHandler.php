<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class RemoveWeighInHandler implements CommandHandler
{
    public function __construct(private WeighInRepository $weighIns)
    {
    }

    public function __invoke(RemoveWeighIn $command): void
    {
        $weighIn = $this->weighIns->on($command->userId, $command->day);
        if (null !== $weighIn) {
            $this->weighIns->remove($weighIn);
        }
    }
}
