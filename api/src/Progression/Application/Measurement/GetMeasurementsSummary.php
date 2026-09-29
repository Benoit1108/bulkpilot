<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;

/**
 * Each measure over a period (spec §54–55): its values, the latest ever, the change over the period.
 *
 * @implements Query<list<MeasureSeries>>
 */
final readonly class GetMeasurementsSummary implements Query
{
    public function __construct(public UserId $userId, public Period $period, public \DateTimeImmutable $today)
    {
    }
}
