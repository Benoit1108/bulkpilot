<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Shared;

use App\Nutrition\Domain\Exception\InvalidJournalDay;
use App\Shared\Presentation\Api\CalendarDays;

/**
 * Calendar days travel as YYYY-MM-DD strings, parsed strictly (2026-02-30 is refused).
 */
final class Days
{
    public const string PATTERN = CalendarDays::PATTERN;

    public static function parse(mixed $value): \DateTimeImmutable
    {
        return CalendarDays::parse($value) ?? throw InvalidJournalDay::because('a day is written YYYY-MM-DD');
    }
}
