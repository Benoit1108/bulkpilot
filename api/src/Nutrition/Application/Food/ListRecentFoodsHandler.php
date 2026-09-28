<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListRecentFoodsHandler implements QueryHandler
{
    public function __construct(private JournalEntryRepository $entries, private FoodRepository $foods)
    {
    }

    /**
     * @return list<FoodView>
     */
    public function __invoke(ListRecentFoods $query): array
    {
        $ids = $this->entries->recentFoods($query->userId, ListRecentFoods::LIMIT);

        return FoodView::list($this->foods->visibleAmong($query->userId, $ids), $query->userId);
    }
}
