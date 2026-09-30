<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Training\Application\Program\GetNextWorkout;

/**
 * @implements ProviderInterface<NextWorkout>
 */
final readonly class NextWorkoutProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NextWorkout
    {
        return NextWorkout::of($this->queryBus->ask(new GetNextWorkout($this->currentUser->id())));
    }
}
