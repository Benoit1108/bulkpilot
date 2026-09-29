<?php

declare(strict_types=1);

namespace App\Training\Application\Workout;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exception\WorkoutInProgress;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutRepository;
use Psr\Clock\ClockInterface;

final readonly class StartWorkoutHandler implements CommandHandler
{
    public function __construct(private WorkoutRepository $workouts, private WorkoutPlanner $planner, private ClockInterface $clock)
    {
    }

    /**
     * Two devices starting at once: the unique index on the workout in progress refuses the
     * second one (409, the device then resumes the first).
     *
     * @throws WorkoutInProgress
     */
    public function __invoke(StartWorkout $command): void
    {
        $existing = $this->workouts->find($command->workoutId);
        if (null !== $existing) {
            // Someone else's, or one discarded since: this id cannot start a workout any more.
            if (!$existing->belongsTo($command->userId) || $existing->isDiscarded()) {
                throw InvalidWorkout::because('this workout id is already taken');
            }

            return;
        }
        $current = $this->workouts->inProgressOf($command->userId);
        if (null !== $current) {
            throw WorkoutInProgress::already($current->id()->value);
        }

        $plan = null === $command->programId ? null : $this->planner->day($command->userId, $command->programId, $command->versionNumber, $command->dayIndex);
        $this->workouts->save(Workout::start($command->workoutId, $command->userId, $plan?->programDay, $plan->exercises ?? [], $command->startedAt, $this->clock->now()));
    }
}
