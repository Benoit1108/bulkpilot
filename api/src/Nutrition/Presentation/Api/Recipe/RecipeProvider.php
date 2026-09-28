<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Recipe;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Recipe\GetRecipe;
use App\Nutrition\Application\Recipe\ListRecipes;
use App\Nutrition\Domain\Exception\RecipeNotFound;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Presentation\Api\Shared\Ids;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<Recipe>
 */
final readonly class RecipeProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Recipe|list<Recipe>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Recipe|array
    {
        $userId = $this->currentUser->id();
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(Recipe::fromView(...), $this->queryBus->ask(new ListRecipes($userId)));
        }

        return Recipe::fromView($this->queryBus->ask(new GetRecipe($userId, self::recipeId($uriVariables['id'] ?? null))));
    }

    public static function recipeId(mixed $id): RecipeId
    {
        return Ids::parse($id, RecipeId::class, RecipeNotFound::withId(...));
    }
}
