<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Logs every item of a favourite in a meal, in one go (one journal entry per item).
 */
final readonly class LogFavoriteMeal implements Command
{
    public function __construct(
        public FavoriteMealId $favoriteId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public MealSlot $slot,
    ) {
    }
}
