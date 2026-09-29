<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class ListWorkoutsHandler implements QueryHandler
{
    public const int MAX = 50;

    public function __construct(private WorkoutRepository $workouts)
    {
    }

    /**
     * @return list<WorkoutSummaryView>
     */
    public function __invoke(ListWorkouts $query): array
    {
        return array_map(WorkoutSummaryView::of(...), $this->workouts->historyOf($query->userId, max(1, min(self::MAX, $query->limit)), $query->before));
    }
}
