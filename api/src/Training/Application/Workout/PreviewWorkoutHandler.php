<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class PreviewWorkoutHandler implements QueryHandler
{
    public function __construct(private WorkoutPlanner $planner, private WorkoutViews $views)
    {
    }

    public function __invoke(PreviewWorkout $query): WorkoutView
    {
        return $this->views->ofPlan($this->planner->day($query->userId, $query->programId, null, $query->dayIndex), $query->userId);
    }
}
