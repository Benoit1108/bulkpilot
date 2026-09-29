<?php

declare(strict_types=1);

namespace App\Shared\Domain\Calendar;

/**
 * The spans a chart can show (spec §55), ending on the user's today.
 */
enum Period: string
{
    case Week = '7d';
    case Month = '30d';
    case Quarter = '3m';
    case HalfYear = '6m';
    case Year = '1y';
    case All = 'all';

    /** The first day shown, today included in the count; null for everything recorded. */
    public function firstDay(\DateTimeImmutable $today): ?\DateTimeImmutable
    {
        $days = match ($this) {
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 91,
            self::HalfYear => 182,
            self::Year => 365,
            self::All => null,
        };

        return null === $days ? null : $today->setTime(0, 0)->modify(\sprintf('-%d days', $days - 1));
    }
}
