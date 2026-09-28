<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListCustomFoodsHandler implements QueryHandler
{
    public function __construct(private FoodRepository $foods)
    {
    }

    /**
     * @return list<FoodView>
     */
    public function __invoke(ListCustomFoods $query): array
    {
        return FoodView::list($this->foods->customFoodsOf($query->userId), $query->userId);
    }
}
