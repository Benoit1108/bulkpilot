<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\DeleteCustomFood;
use App\Nutrition\Application\Food\EditCustomFood;
use App\Nutrition\Application\Food\GetFood;
use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProcessorInterface<CustomFoodInput|null, Food|null>
 */
final readonly class CustomFoodProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Food
    {
        $userId = $this->currentUser->id();

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DeleteCustomFood(FoodProvider::foodId($uriVariables['id'] ?? null), $userId));

            return null;
        }
        \assert($data instanceof CustomFoodInput);

        if ($operation instanceof Post) {
            $foodId = new FoodId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateCustomFood($foodId, $userId, $data->details()));
        } else {
            $foodId = FoodProvider::foodId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch(new EditCustomFood($foodId, $userId, $data->details()));
        }

        return Food::fromView($this->queryBus->ask(new GetFood($userId, $foodId)));
    }
}
