<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<WeightSummaryView>
 */
final readonly class GetWeightSummary implements Query
{
    /**
     * @param \DateTimeImmutable $today the user's (their device's) today
     */
    public function __construct(public UserId $userId, public Period $period, public \DateTimeImmutable $today)
    {
    }
}
