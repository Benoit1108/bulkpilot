<?php

declare(strict_types=1);

namespace App\Progression\Domain;

/**
 * The days a record can be for: any day already come, since 2000. The device's today may be a day
 * ahead of the server's (time zones east of it).
 */
final class DayWindow
{
    public const int LATEST_DAYS_AHEAD = 1;
    public const string EARLIEST_DAY = '2000-01-01';

    public static function allows(\DateTimeImmutable $day, \DateTimeImmutable $now): bool
    {
        $day = $day->setTime(0, 0);

        return $day <= $now->setTime(0, 0)->modify(\sprintf('+%d day', self::LATEST_DAYS_AHEAD)) && $day >= new \DateTimeImmutable(self::EARLIEST_DAY);
    }
}
