<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Keeps what was logged in a meal of a day as a favourite ("Enregistrer ce repas").
 */
final readonly class SaveFavoriteMeal implements Command
{
    public function __construct(
        public FavoriteMealId $favoriteId,
        public UserId $userId,
        public string $name,
        public \DateTimeImmutable $day,
        public MealSlot $slot,
    ) {
    }
}
