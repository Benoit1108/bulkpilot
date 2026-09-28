<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<FoodView>>
 */
final readonly class ListCustomFoods implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
