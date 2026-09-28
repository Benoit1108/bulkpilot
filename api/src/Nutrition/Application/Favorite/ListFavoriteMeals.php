<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<FavoriteMealView>>
 */
final readonly class ListFavoriteMeals implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
