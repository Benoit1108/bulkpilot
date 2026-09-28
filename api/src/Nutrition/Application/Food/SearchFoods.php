<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<FoodView>>
 */
final readonly class SearchFoods implements Query
{
    public const int MAX_RESULTS = 30;
    public const int DEFAULT_RESULTS = 20;

    public function __construct(public UserId $userId, public string $query, public int $limit = self::DEFAULT_RESULTS)
    {
    }
}
