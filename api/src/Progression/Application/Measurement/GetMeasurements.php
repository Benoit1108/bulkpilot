<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<MeasurementDayView>
 */
final readonly class GetMeasurements implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day)
    {
    }
}
