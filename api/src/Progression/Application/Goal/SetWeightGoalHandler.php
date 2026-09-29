<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Progression\Domain\Exception\InvalidWeightGoal;
use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\Weight;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SetWeightGoalHandler implements CommandHandler
{
    public function __construct(private WeightGoalRepository $goals, private WeighInRepository $weighIns, private ClockInterface $clock)
    {
    }

    public function __invoke(SetWeightGoal $command): void
    {
        $latest = $this->weighIns->latest($command->userId, 1)[0] ?? throw InvalidWeightGoal::because('a goal starts from a weigh-in: record one first');
        $now = $this->clock->now();

        $goal = WeightGoal::set(
            $command->goalId,
            $command->userId,
            DayWeight::of($latest),
            new Weight($command->targetKg),
            array_map(static fn (float $kg): Weight => new Weight($kg), $command->milestonesKg),
            $now,
        );
        $current = $this->goals->current($command->userId);
        if (null !== $current) {
            $current->end($now);
            $this->goals->save($current);
        }
        $this->goals->save($goal);
    }
}
