<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The training of the week under way (working sets by muscle) and of the last full week (its
 * sessions and tonnage), whatever period the other figures cover.
 *
 * @implements Query<TrainingWeekView>
 */
final readonly class GetTrainingWeek implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $today)
    {
    }
}
