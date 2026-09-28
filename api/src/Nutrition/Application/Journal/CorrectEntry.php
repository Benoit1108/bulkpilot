<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Fixes the quantity, or moves the entry to another meal or day (spec §20: "modifier une entrée").
 */
final readonly class CorrectEntry implements Command
{
    public function __construct(
        public JournalEntryId $entryId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public MealSlot $slot,
        public float $grams,
    ) {
    }
}
