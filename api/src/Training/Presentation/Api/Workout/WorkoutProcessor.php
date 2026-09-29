<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use App\Training\Application\Workout\DiscardWorkout;
use App\Training\Application\Workout\FinishWorkout;
use App\Training\Application\Workout\GetWorkout;
use App\Training\Application\Workout\StartWorkout;
use App\Training\Domain\Program\ProgramId;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<StartWorkoutInput|FinishWorkoutInput|null, Workout|null>
 */
final readonly class WorkoutProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private QueryBus $queryBus, private CurrentUser $currentUser, private RateLimiterFactoryInterface $trainingWritesLimiter)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Workout
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->trainingWritesLimiter, 'training_writes|'.$userId->value);
        $workoutId = WorkoutProvider::workoutId($uriVariables['id'] ?? null);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DiscardWorkout($workoutId, $userId));

            return null;
        }
        if ($data instanceof FinishWorkoutInput) {
            \assert(null !== $data->finishedAt);
            $this->commandBus->dispatch(new FinishWorkout($workoutId, $userId, $data->finishedAt));
        } else {
            \assert($data instanceof StartWorkoutInput && null !== $data->startedAt);
            $programId = null === $data->programId ? null : new ProgramId($data->programId);
            $this->commandBus->dispatch(new StartWorkout($workoutId, $userId, $data->startedAt, $programId, $data->versionNumber, $data->dayIndex));
        }

        return Workout::fromView($this->queryBus->ask(new GetWorkout($userId, $workoutId)));
    }
}
