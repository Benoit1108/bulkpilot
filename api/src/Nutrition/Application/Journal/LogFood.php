<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class LogFood implements Command
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $userId,
        public FoodId $foodId,
        public \DateTimeImmutable $day,
        public MealSlot $slot,
        public float $grams,
    ) {
    }
}
