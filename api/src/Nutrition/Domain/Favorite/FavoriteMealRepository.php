<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Favorite;

use App\Nutrition\Domain\Exception\FavoriteMealNotFound;
use App\Shared\Domain\Identifier\UserId;

interface FavoriteMealRepository
{
    public function save(FavoriteMeal $meal): void;

    public function remove(FavoriteMeal $meal): void;

    /**
     * @throws FavoriteMealNotFound also when it belongs to someone else
     */
    public function getOwnedBy(FavoriteMealId $id, UserId $userId): FavoriteMeal;

    /**
     * @return list<FavoriteMeal> by name
     */
    public function favoritesOf(UserId $userId): array;
}
