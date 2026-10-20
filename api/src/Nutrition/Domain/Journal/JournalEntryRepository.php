<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Exception\JournalEntryNotFound;
use App\Shared\Domain\Identifier\UserId;

interface JournalEntryRepository
{
    public function save(JournalEntry $entry): void;

    public function remove(JournalEntry $entry): void;

    /**
     * @throws JournalEntryNotFound also when it belongs to someone else
     */
    public function getOwnedBy(JournalEntryId $id, UserId $userId): JournalEntry;

    /**
     * @return list<JournalEntry> in logging order
     */
    public function ofDay(UserId $userId, \DateTimeImmutable $day): array;

    /**
     * Daily totals of the days that have at least one entry, between two days (inclusive).
     *
     * @return array<string, DayTotals> keyed by Y-m-d
     */
    public function dailyTotals(UserId $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Foods most recently logged by the user, most recent first, each with its latest quantity.
     *
     * @return list<RecentFood>
     */
    public function recentFoods(UserId $userId, int $limit): array;
}
