<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Favorite;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Favorite\DeleteFavoriteMeal;
use App\Nutrition\Application\Favorite\FavoriteMealView;
use App\Nutrition\Application\Favorite\ListFavoriteMeals;
use App\Nutrition\Application\Favorite\SaveFavoriteMeal;
use App\Nutrition\Domain\Exception\FavoriteMealNotFound;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Nutrition\Presentation\Api\Shared\Ids;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProviderInterface<FavoriteMeal>
 * @implements ProcessorInterface<SaveFavoriteInput|null, FavoriteMeal|null>
 */
final readonly class FavoriteMealState implements ProviderInterface, ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
    ) {
    }

    /**
     * @return list<FavoriteMeal>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(FavoriteMeal::fromView(...), $this->favorites());
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FavoriteMeal
    {
        $userId = $this->currentUser->id();

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new DeleteFavoriteMeal(self::favoriteId($uriVariables['id'] ?? null), $userId));

            return null;
        }
        \assert($data instanceof SaveFavoriteInput);

        $favoriteId = new FavoriteMealId($this->uuids->generate());
        $this->commandBus->dispatch(new SaveFavoriteMeal($favoriteId, $userId, $data->name, Days::parse($data->day), MealSlot::from($data->slot)));

        $created = array_find($this->favorites(), static fn (FavoriteMealView $view): bool => $view->id === $favoriteId->value);
        \assert(null !== $created);

        return FavoriteMeal::fromView($created);
    }

    public static function favoriteId(mixed $id): FavoriteMealId
    {
        return Ids::parse($id, FavoriteMealId::class, FavoriteMealNotFound::withId(...));
    }

    /**
     * @return list<FavoriteMealView>
     */
    private function favorites(): array
    {
        return $this->queryBus->ask(new ListFavoriteMeals($this->currentUser->id()));
    }
}
