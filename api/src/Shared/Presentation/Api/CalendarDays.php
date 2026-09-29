<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api;

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Calendar days travel as YYYY-MM-DD strings, parsed strictly (2026-02-30 is refused). Each
 * context says what a wrong day in its own terms means; `today()` reads the user's today.
 */
final class CalendarDays
{
    public const string PATTERN = '\d{4}-\d{2}-\d{2}';

    public static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $day && $day->format('Y-m-d') === $value ? $day : null;
    }

    /**
     * The user's today, sent by their device (`?today=`), which may be a day off the server's
     * (time zones); the server's when absent. Anything else is refused.
     */
    public static function today(?string $value, ClockInterface $clock): \DateTimeImmutable
    {
        $serverToday = $clock->now()->setTime(0, 0);
        if (null === $value || '' === $value) {
            return $serverToday;
        }
        $day = self::parse($value);
        if (null === $day || abs((int) $serverToday->diff($day)->format('%r%a')) > 1) {
            throw new BadRequestHttpException('"today" is the device\'s YYYY-MM-DD date, within a day of the server\'s.');
        }

        return $day;
    }
}
