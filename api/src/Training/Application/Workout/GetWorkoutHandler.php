<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class GetWorkoutHandler implements QueryHandler
{
    public function __construct(private WorkoutRepository $workouts, private WorkoutViews $views)
    {
    }

    public function __invoke(GetWorkout $query): WorkoutView
    {
        return $this->views->of($this->workouts->getVisibleTo($query->workoutId, $query->userId), $query->userId);
    }
}
