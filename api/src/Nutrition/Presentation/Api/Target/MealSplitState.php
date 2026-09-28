<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Target\ChangeMealSplit;
use App\Nutrition\Application\Target\GetMealSplit;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<MealSplit>
 * @implements ProcessorInterface<MealSplit, MealSplit>
 */
final readonly class MealSplitState implements ProviderInterface, ProcessorInterface
{
    public function __construct(private CommandBus $commandBus, private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MealSplit
    {
        return MealSplit::of($this->queryBus->ask(new GetMealSplit($this->currentUser->id())));
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MealSplit
    {
        $this->commandBus->dispatch(new ChangeMealSplit($this->currentUser->id(), $data->shares()));

        return $this->provide($operation);
    }
}
