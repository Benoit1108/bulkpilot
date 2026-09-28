<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Shared;

use App\Nutrition\Domain\Exception\InvalidJournalDay;

/**
 * Calendar days travel as YYYY-MM-DD strings, parsed strictly (2026-02-30 is refused).
 */
final class Days
{
    public const string PATTERN = '\d{4}-\d{2}-\d{2}';

    public static function parse(mixed $value): \DateTimeImmutable
    {
        if (\is_string($value)) {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (false !== $day && $day->format('Y-m-d') === $value) {
                return $day;
            }
        }

        throw InvalidJournalDay::because('a day is written YYYY-MM-DD');
    }
}
