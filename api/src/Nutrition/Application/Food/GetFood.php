<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<FoodView>
 */
final readonly class GetFood implements Query
{
    public function __construct(public UserId $userId, public FoodId $foodId)
    {
    }
}
