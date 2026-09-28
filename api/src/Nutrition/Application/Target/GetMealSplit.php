<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<array<value-of<MealSlot>, int>>
 */
final readonly class GetMealSplit implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
