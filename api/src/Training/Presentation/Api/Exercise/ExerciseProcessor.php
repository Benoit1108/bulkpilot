<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use App\Training\Application\Exercise\CreateExercise;
use App\Training\Application\Exercise\DeleteExercise;
use App\Training\Application\Exercise\EditExercise;
use App\Training\Application\Exercise\GetExercise;
use App\Training\Application\Exercise\WriteExerciseNote;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNoteId;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ExerciseInput|ExerciseNoteInput|null, Exercise|null>
 */
final readonly class ExerciseProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $trainingWritesLimiter,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Exercise
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->trainingWritesLimiter, 'training_writes|'.$userId->value);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DeleteExercise(ExerciseProvider::exerciseId($uriVariables['id'] ?? null), $userId));

            return null;
        }

        if ($data instanceof ExerciseNoteInput) {
            $exerciseId = ExerciseProvider::exerciseId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch(new WriteExerciseNote($userId, $exerciseId, $data->text, new ExerciseNoteId($this->uuids->generate())));
        } elseif ($operation instanceof Post) {
            \assert($data instanceof ExerciseInput);
            $exerciseId = new ExerciseId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateExercise($exerciseId, $userId, $data->details()));
        } else {
            \assert($data instanceof ExerciseInput);
            $exerciseId = ExerciseProvider::exerciseId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch(new EditExercise($exerciseId, $userId, $data->details()));
        }

        return Exercise::fromView($this->queryBus->ask(new GetExercise($userId, $exerciseId)));
    }
}
