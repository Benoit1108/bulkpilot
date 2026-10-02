<?php

declare(strict_types=1);

namespace App\Coach\Application\Report;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<WeeklyReportView>>
 */
final readonly class ListWeeklyReports implements Query
{
    /** A page by default, a year of weeks at most. */
    public const int PAGE = 12;
    public const int MAX = 52;

    public function __construct(public UserId $userId, public int $limit = self::PAGE, public ?\DateTimeImmutable $before = null)
    {
    }
}
