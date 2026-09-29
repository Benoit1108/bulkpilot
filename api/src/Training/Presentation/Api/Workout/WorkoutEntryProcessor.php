<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;
use App\Shared\Presentation\Http\RateLimit;
use App\Training\Application\Workout\AddWorkoutExercise;
use App\Training\Application\Workout\RecordSet;
use App\Training\Application\Workout\RemoveSet;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<WorkoutSetInput|AddWorkoutExerciseInput|null, null>
 */
final readonly class WorkoutEntryProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private CurrentUser $currentUser, private RateLimiterFactoryInterface $trainingWritesLimiter)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->trainingWritesLimiter, 'training_writes|'.$userId->value);
        $workoutId = WorkoutProvider::workoutId($uriVariables['workoutId'] ?? null);

        if ($data instanceof AddWorkoutExerciseInput) {
            $index = $uriVariables['index'] ?? null;
            $this->commandBus->dispatch(new AddWorkoutExercise($workoutId, $userId, is_numeric($index) ? (int) $index : 0, new ExerciseId($data->exerciseId)));

            return null;
        }
        $setId = Ids::parse($uriVariables['id'] ?? null, SetId::class, WorkoutNotFound::withId(...));
        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new RemoveSet($workoutId, $userId, $setId));

            return null;
        }
        \assert($data instanceof WorkoutSetInput && null !== $data->exerciseIndex && null !== $data->doneAt);
        $this->commandBus->dispatch(new RecordSet($workoutId, $userId, $setId, $data->exerciseIndex, SetKind::from($data->kind), $data->loadKg, $data->reps, $data->seconds, $data->rir, $data->doneAt));

        return null;
    }
}
