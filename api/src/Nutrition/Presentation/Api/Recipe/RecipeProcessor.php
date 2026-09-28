<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Recipe\CreateRecipe;
use App\Nutrition\Application\Recipe\DeleteRecipe;
use App\Nutrition\Application\Recipe\EditRecipe;
use App\Nutrition\Application\Recipe\GetRecipe;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProcessorInterface<RecipeInput|null, Recipe|null>
 */
final readonly class RecipeProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Recipe
    {
        $userId = $this->currentUser->id();

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DeleteRecipe(RecipeProvider::recipeId($uriVariables['id'] ?? null), $userId));

            return null;
        }
        \assert($data instanceof RecipeInput);

        if ($operation instanceof Post) {
            $recipeId = new RecipeId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateRecipe($recipeId, $userId, $data->details()));
        } else {
            $recipeId = RecipeProvider::recipeId($uriVariables['id'] ?? null);
            $this->commandBus->dispatch(new EditRecipe($recipeId, $userId, $data->details()));
        }

        return Recipe::fromView($this->queryBus->ask(new GetRecipe($userId, $recipeId)));
    }
}
