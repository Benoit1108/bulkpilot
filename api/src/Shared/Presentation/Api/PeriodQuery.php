<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api;

use App\Shared\Domain\Calendar\Period;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * `?period=` (3 months by default) and `?today=` of a chart's request.
 */
final class PeriodQuery
{
    /**
     * @return array{Period, \DateTimeImmutable}
     */
    public static function read(?Request $request, ClockInterface $clock): array
    {
        $query = $request?->query;
        $period = Period::tryFrom($query?->getString('period', Period::Quarter->value) ?? Period::Quarter->value)
            ?? throw new BadRequestHttpException('"period" is one of 7d, 30d, 3m, 6m, 1y, all.');

        return [$period, CalendarDays::today($query?->getString('today'), $clock)];
    }
}
