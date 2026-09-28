<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class DeleteFavoriteMeal implements Command
{
    public function __construct(public FavoriteMealId $favoriteId, public UserId $userId)
    {
    }
}
