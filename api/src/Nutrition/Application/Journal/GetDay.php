<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<DayView>
 */
final readonly class GetDay implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day)
    {
    }
}
