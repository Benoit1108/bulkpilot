<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Training\Application\Progress\GetExerciseProgress;

/**
 * @implements ProviderInterface<ExerciseProgress>
 */
final readonly class ExerciseProgressProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ExerciseProgress
    {
        return ExerciseProgress::fromView($this->queryBus->ask(new GetExerciseProgress($this->currentUser->id(), ExerciseProvider::exerciseId($uriVariables['id'] ?? null))));
    }
}
