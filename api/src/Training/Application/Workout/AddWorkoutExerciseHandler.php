<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class AddWorkoutExerciseHandler implements CommandHandler
{
    public function __construct(private WorkoutRepository $workouts, private WorkoutPlanner $planner)
    {
    }

    public function __invoke(AddWorkoutExercise $command): void
    {
        $workout = $this->workouts->getVisibleTo($command->workoutId, $command->userId);
        $exercise = $this->planner->usual($command->userId, $command->exerciseId);
        $workout->addExercise($command->index, $exercise->exerciseId, $exercise->name, $exercise->measurement, $exercise->planned);
        $this->workouts->save($workout);
    }
}
