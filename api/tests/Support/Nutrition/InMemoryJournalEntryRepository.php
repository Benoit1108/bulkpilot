<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Exception\JournalEntryNotFound;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\NutrientAmounts;
use App\Nutrition\Domain\Journal\DayTotals;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Nutrition\Domain\Journal\RecentFood;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryJournalEntryRepository implements JournalEntryRepository
{
    /** @var array<string, JournalEntry> */
    public array $entries = [];

    public function save(JournalEntry $entry): void
    {
        $this->entries[$entry->id()->value] = $entry;
    }

    public function remove(JournalEntry $entry): void
    {
        unset($this->entries[$entry->id()->value]);
    }

    public function getOwnedBy(JournalEntryId $id, UserId $userId): JournalEntry
    {
        $entry = $this->entries[$id->value] ?? null;
        if (null === $entry || !$entry->belongsTo($userId)) {
            throw JournalEntryNotFound::withId($id->value);
        }

        return $entry;
    }

    public function ofDay(UserId $userId, \DateTimeImmutable $day): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (JournalEntry $entry): bool => $entry->belongsTo($userId) && $entry->day()->format('Y-m-d') === $day->format('Y-m-d'),
        ));
    }

    public function dailyTotals(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $totals = [];
        foreach ($this->entries as $entry) {
            $key = $entry->day()->format('Y-m-d');
            if ($entry->belongsTo($userId) && $key >= $from->format('Y-m-d') && $key <= $to->format('Y-m-d')) {
                $totals[$key] = new DayTotals($entry->day(), ($totals[$key]->amounts ?? NutrientAmounts::zero())->add($entry->amounts()));
            }
        }
        ksort($totals);

        return $totals;
    }

    public function recentFoods(UserId $userId, int $limit): array
    {
        $entries = array_filter($this->entries, static fn (JournalEntry $entry): bool => $entry->belongsTo($userId) && null !== $entry->food()->foodId);
        // As the SQL: most recent first, time-ordered ids settle entries logged at once.
        usort($entries, static fn (JournalEntry $a, JournalEntry $b): int => [$b->loggedAt(), $b->id()->value] <=> [$a->loggedAt(), $a->id()->value]);

        $recent = [];
        foreach ($entries as $entry) {
            $id = $entry->food()->foodId;
            \assert($id instanceof FoodId);
            $recent[$id->value] ??= new RecentFood($id, $entry->grams());
        }

        return \array_slice(array_values($recent), 0, $limit);
    }
}
