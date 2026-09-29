<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Workout\WorkoutRepository;

final readonly class RemoveSetHandler implements CommandHandler
{
    public function __construct(private WorkoutRepository $workouts)
    {
    }

    public function __invoke(RemoveSet $command): void
    {
        $workout = $this->workouts->getVisibleTo($command->workoutId, $command->userId);
        $workout->removeSet($command->setId);
        $this->workouts->save($workout);
    }
}
