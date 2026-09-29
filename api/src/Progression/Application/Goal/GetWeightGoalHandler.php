<?php

declare(strict_types=1);

namespace App\Progression\Application\Goal;

use App\Progression\Application\Weight\WeightReadings;
use App\Progression\Domain\Exception\WeightGoalNotFound;
use App\Progression\Domain\Goal\GoalProgress;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetWeightGoalHandler implements QueryHandler
{
    public function __construct(private WeightGoalRepository $goals, private WeightReadings $readings)
    {
    }

    public function __invoke(GetWeightGoal $query): GoalProgress
    {
        $today = $query->today->setTime(0, 0);
        $goal = $this->goals->current($query->userId) ?? throw WeightGoalNotFound::current();
        $since = WeightReadings::forGoal($goal, $today);

        // A goal starts from a weigh-in, which may since have been removed: then there is nothing to read.
        return WeightReadings::progress($goal, $this->readings->since($query->userId, $since), $today)
            ?? throw WeightGoalNotFound::current();
    }
}
