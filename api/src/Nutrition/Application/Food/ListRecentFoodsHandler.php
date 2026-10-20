<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\RecentFood;
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
        $recent = $this->entries->recentFoods($query->userId, ListRecentFoods::LIMIT);
        $grams = [];
        foreach ($recent as $food) {
            $grams[$food->foodId->value] = $food->lastGrams;
        }
        $foods = $this->foods->visibleAmong($query->userId, array_map(static fn (RecentFood $food): FoodId => $food->foodId, $recent));

        return array_map(static fn (Food $food): FoodView => FoodView::of($food, $query->userId, $grams[$food->id()->value] ?? null), $foods);
    }
}
