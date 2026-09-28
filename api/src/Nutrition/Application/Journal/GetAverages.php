<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<AveragesView>
 */
final readonly class GetAverages implements Query
{
    public const array PERIODS = [7, 30];

    /**
     * @param int $days period ending yesterday: today is still being logged, it would pull the average down
     */
    public function __construct(public UserId $userId, public \DateTimeImmutable $today, public int $days)
    {
    }
}
