<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\SearchKey;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class SearchFoodsHandler implements QueryHandler
{
    /** Shorter queries match too much of the catalogue to be useful. */
    public const int MIN_QUERY_LENGTH = 2;

    public function __construct(private FoodRepository $foods)
    {
    }

    /**
     * @return list<FoodView>
     */
    public function __invoke(SearchFoods $query): array
    {
        $key = SearchKey::of($query->query);
        if (mb_strlen($key) < self::MIN_QUERY_LENGTH) {
            return [];
        }
        $limit = max(1, min(SearchFoods::MAX_RESULTS, $query->limit));

        return FoodView::list($this->foods->search($query->userId, $key, $limit), $query->userId);
    }
}
