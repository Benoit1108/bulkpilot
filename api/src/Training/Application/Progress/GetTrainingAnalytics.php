<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;

/**
 * What the training of a period amounts to (spec §57): sessions, frequency, working sets, reps,
 * tonnage and records beaten, week by week. No "muscle balance" (spec §57).
 *
 * @implements Query<TrainingAnalyticsView>
 */
final readonly class GetTrainingAnalytics implements Query
{
    public function __construct(public UserId $userId, public Period $period, public \DateTimeImmutable $today)
    {
    }
}
