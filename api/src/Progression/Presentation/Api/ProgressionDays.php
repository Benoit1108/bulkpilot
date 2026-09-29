<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api;

use App\Progression\Domain\Exception\InvalidDay;
use App\Shared\Presentation\Api\CalendarDays;

/**
 * The day of a weigh-in or of measurements, in the path.
 */
final class ProgressionDays
{
    public static function parse(mixed $value): \DateTimeImmutable
    {
        return CalendarDays::parse($value) ?? throw InvalidDay::notADay();
    }
}
