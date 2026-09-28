<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

use App\Nutrition\Domain\Exception\InvalidJournalDay;

/**
 * The day is the user's calendar day, sent by the client (the server does not know the user's
 * time zone). It is only checked for plausibility: not further than tomorrow (time zones ahead
 * of the server), not older than the history the app keeps.
 */
final class JournalDays
{
    public const int MAX_PAST_DAYS = 3660;

    public function assertLoggable(\DateTimeImmutable $day, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $day = $day->setTime(0, 0);
        $today = $now->setTime(0, 0);
        if ($day > $today->modify('+1 day') || $day < $today->modify(\sprintf('-%d days', self::MAX_PAST_DAYS))) {
            throw InvalidJournalDay::because('the day is too far from today');
        }

        return $day;
    }
}
