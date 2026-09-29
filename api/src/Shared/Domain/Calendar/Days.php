<?php

declare(strict_types=1);

namespace App\Shared\Domain\Calendar;

/**
 * Calendar days (midnight, no time of day) and the number of days between them.
 */
final class Days
{
    /** Whole days from $from to $to: negative when $to comes first. */
    public static function between(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->format('%r%a');
    }

    /** The Monday of the week the day falls in (ISO weeks). */
    public static function weekOf(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0)->modify(\sprintf('-%d days', (int) $day->format('N') - 1));
    }
}
