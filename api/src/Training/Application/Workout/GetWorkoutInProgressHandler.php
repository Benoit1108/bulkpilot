<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class GetWorkoutInProgressHandler implements QueryHandler
{
    public function __construct(private WorkoutRepository $workouts, private WorkoutViews $views)
    {
    }

    public function __invoke(GetWorkoutInProgress $query): ?WorkoutView
    {
        $workout = $this->workouts->inProgressOf($query->userId);

        return null === $workout ? null : $this->views->of($workout, $query->userId);
    }
}
