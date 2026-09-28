<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Journal\JournalDays;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;

/**
 * The one way an entry enters the journal, whatever it comes from (food, recipe, favourite,
 * Meal Builder proposal): plausible day, frozen values, logging time.
 */
final readonly class JournalWriter
{
    public function __construct(private JournalEntryRepository $entries, private JournalDays $days, private ClockInterface $clock)
    {
    }

    public function log(JournalEntryId $id, UserId $userId, \DateTimeImmutable $day, MealSlot $slot, LoggedFood $food, float $grams): void
    {
        $now = $this->clock->now();
        $this->entries->save(JournalEntry::log($id, $userId, $this->days->assertLoggable($day, $now), $slot, $food, $grams, $now));
    }
}
