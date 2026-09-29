<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Food;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Application\Food\GetFood;
use App\Nutrition\Application\Food\ListCustomFoods;
use App\Nutrition\Application\Food\ListRecentFoods;
use App\Nutrition\Application\Food\SearchFoods;
use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Presentation\Api\Ids;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Food>
 */
final readonly class FoodProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Food|list<Food>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Food|array
    {
        $userId = $this->currentUser->id();
        if (!$operation instanceof CollectionOperationInterface) {
            return Food::fromView($this->queryBus->ask(new GetFood($userId, self::foodId($uriVariables['id'] ?? null))));
        }

        $views = match ($operation->getName()) {
            'foods_recent' => $this->queryBus->ask(new ListRecentFoods($userId)),
            'foods_custom' => $this->queryBus->ask(new ListCustomFoods($userId)),
            default => $this->search($userId, $context['request'] ?? null),
        };

        return array_map(Food::fromView(...), $views);
    }

    public static function foodId(mixed $id): FoodId
    {
        return Ids::parse($id, FoodId::class, FoodNotFound::withId(...));
    }

    /**
     * @return list<FoodView>
     */
    private function search(UserId $userId, mixed $request): array
    {
        $query = $request instanceof Request ? $request->query : null;

        return $this->queryBus->ask(new SearchFoods(
            $userId,
            mb_substr((string) $query?->getString('q'), 0, 100),
            $query?->getInt('limit', SearchFoods::DEFAULT_RESULTS) ?? SearchFoods::DEFAULT_RESULTS,
        ));
    }
}
