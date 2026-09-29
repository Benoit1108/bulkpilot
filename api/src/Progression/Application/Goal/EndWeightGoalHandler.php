<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class EndWeightGoalHandler implements CommandHandler
{
    public function __construct(private WeightGoalRepository $goals, private ClockInterface $clock)
    {
    }

    public function __invoke(EndWeightGoal $command): void
    {
        $goal = $this->goals->current($command->userId);
        if (null !== $goal) {
            $goal->end($this->clock->now());
            $this->goals->save($goal);
        }
    }
}
