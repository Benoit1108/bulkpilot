<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use App\Training\Application\Program\ActivateProgram;
use App\Training\Application\Program\CreateProgram;
use App\Training\Application\Program\DeactivateProgram;
use App\Training\Application\Program\DeleteProgram;
use App\Training\Application\Program\EditProgram;
use App\Training\Application\Program\GetProgram;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ProgramInput|null, Program|null>
 */
final readonly class ProgramProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $trainingWritesLimiter,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Program
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->trainingWritesLimiter, 'training_writes|'.$userId->value);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DeleteProgram(ProgramProvider::programId($uriVariables['id'] ?? null), $userId));

            return null;
        }

        $name = $operation->getName();
        if ('program_activate' === $name || 'program_deactivate' === $name) {
            $programId = ProgramProvider::programId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch('program_activate' === $name ? new ActivateProgram($programId, $userId) : new DeactivateProgram($programId, $userId));
        } elseif ($operation instanceof Post) {
            \assert($data instanceof ProgramInput);
            $programId = new ProgramId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateProgram($programId, new ProgramVersionId($this->uuids->generate()), $userId, $data->details()));
        } else {
            \assert($data instanceof ProgramInput);
            $programId = ProgramProvider::programId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch(new EditProgram($programId, new ProgramVersionId($this->uuids->generate()), $userId, $data->details()));
        }

        return Program::fromView($this->queryBus->ask(new GetProgram($userId, $programId)));
    }
}
