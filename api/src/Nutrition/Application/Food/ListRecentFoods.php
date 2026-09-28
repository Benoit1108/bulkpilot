<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * Foods the user logged lately, for one-tap re-logging (spec §21: "aliments récents"). Among the
 * last LIMIT foods logged, those deleted since are left out (so there can be fewer).
 *
 * @implements Query<list<FoodView>>
 */
final readonly class ListRecentFoods implements Query
{
    public const int LIMIT = 20;

    public function __construct(public UserId $userId)
    {
    }
}
