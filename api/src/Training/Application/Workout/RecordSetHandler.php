<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Workout\WorkoutRepository;
use Psr\Clock\ClockInterface;

final readonly class RecordSetHandler implements CommandHandler
{
    public function __construct(private WorkoutRepository $workouts, private ClockInterface $clock)
    {
    }

    public function __invoke(RecordSet $command): void
    {
        $workout = $this->workouts->getVisibleTo($command->workoutId, $command->userId);
        $workout->recordSet($command->setId, $command->exerciseIndex, $command->kind, $command->loadKg, $command->reps, $command->seconds, $command->rir, $command->doneAt, $this->clock->now());
        $this->workouts->save($workout);
    }
}
