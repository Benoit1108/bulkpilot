<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Favorite\LogFavoriteMeal;
use App\Nutrition\Application\Journal\CorrectEntry;
use App\Nutrition\Application\Journal\GetDay;
use App\Nutrition\Application\Journal\LogFood;
use App\Nutrition\Application\Journal\LogFoods;
use App\Nutrition\Application\Journal\LogItem;
use App\Nutrition\Application\Journal\LogRecipe;
use App\Nutrition\Application\Journal\RemoveEntry;
use App\Nutrition\Domain\Exception\JournalEntryNotFound;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Presentation\Api\Favorite\FavoriteMealState;
use App\Nutrition\Presentation\Api\Food\FoodProvider;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Nutrition\Presentation\Api\Shared\Ids;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProcessorInterface<LogFoodInput|LogFoodsInput|LogFavoriteInput|CorrectEntryInput|null, NutritionDay|null>
 */
final readonly class JournalEntryProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?NutritionDay
    {
        $userId = $this->currentUser->id();

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new RemoveEntry(self::entryId($uriVariables['id'] ?? null), $userId));

            return null;
        }

        if ($data instanceof LogFoodInput) {
            $day = Days::parse($data->day);
            $entryId = new JournalEntryId($this->uuids->generate());
            $this->commandBus->dispatch(null !== $data->recipeId
                ? new LogRecipe($entryId, $userId, new RecipeId($data->recipeId), $day, MealSlot::from($data->slot), (float) $data->grams)
                : new LogFood($entryId, $userId, new FoodId((string) $data->foodId), $day, MealSlot::from($data->slot), (float) $data->grams));
        } elseif ($data instanceof LogFoodsInput) {
            $day = Days::parse($data->day);
            $this->commandBus->dispatch(new LogFoods($userId, $day, MealSlot::from($data->slot), array_map(
                static fn (LogFoodsItemInput $item): LogItem => new LogItem(FoodProvider::foodId($item->foodId), (float) $item->grams),
                $data->items,
            )));
        } elseif ($data instanceof LogFavoriteInput) {
            $day = Days::parse($data->day);
            $this->commandBus->dispatch(new LogFavoriteMeal(FavoriteMealState::favoriteId($uriVariables['id'] ?? null), $userId, $day, MealSlot::from($data->slot)));
        } else {
            \assert($data instanceof CorrectEntryInput);
            $day = Days::parse($data->day);
            $this->commandBus->dispatch(new CorrectEntry(self::entryId($uriVariables['id'] ?? null), $userId, $day, MealSlot::from($data->slot), (float) $data->grams));
        }

        return NutritionDay::fromView($this->queryBus->ask(new GetDay($userId, $day)));
    }

    private static function entryId(mixed $id): JournalEntryId
    {
        return Ids::parse($id, JournalEntryId::class, JournalEntryNotFound::withId(...));
    }
}
