<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Workout\WorkoutRepository;
use Psr\Clock\ClockInterface;

final readonly class DiscardWorkoutHandler implements CommandHandler
{
    public function __construct(private WorkoutRepository $workouts, private ClockInterface $clock)
    {
    }

    public function __invoke(DiscardWorkout $command): void
    {
        // Idempotent: discarding again (a lost answer, sent again) changes nothing.
        $workout = $this->workouts->find($command->workoutId);
        if (null === $workout || !$workout->belongsTo($command->userId)) {
            throw WorkoutNotFound::withId($command->workoutId->value);
        }
        $workout->discard($this->clock->now());
        $this->workouts->save($workout);
    }
}
