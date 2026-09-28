<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetFoodHandler implements QueryHandler
{
    public function __construct(private FoodRepository $foods)
    {
    }

    public function __invoke(GetFood $query): FoodView
    {
        return FoodView::of($this->foods->getVisibleTo($query->foodId, $query->userId), $query->userId);
    }
}
