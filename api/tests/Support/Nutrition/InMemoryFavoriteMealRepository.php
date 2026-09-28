<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Exception\FavoriteMealNotFound;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryFavoriteMealRepository implements FavoriteMealRepository
{
    /** @var array<string, FavoriteMeal> */
    public array $favorites = [];

    public function save(FavoriteMeal $meal): void
    {
        $this->favorites[$meal->id()->value] = $meal;
    }

    public function remove(FavoriteMeal $meal): void
    {
        unset($this->favorites[$meal->id()->value]);
    }

    public function getOwnedBy(FavoriteMealId $id, UserId $userId): FavoriteMeal
    {
        $meal = $this->favorites[$id->value] ?? null;
        if (null === $meal || !$meal->belongsTo($userId)) {
            throw FavoriteMealNotFound::withId($id->value);
        }

        return $meal;
    }

    public function favoritesOf(UserId $userId): array
    {
        $favorites = array_values(array_filter($this->favorites, static fn (FavoriteMeal $meal): bool => $meal->belongsTo($userId)));
        usort($favorites, static fn (FavoriteMeal $a, FavoriteMeal $b): int => mb_strtolower($a->name()) <=> mb_strtolower($b->name()));

        return $favorites;
    }
}
