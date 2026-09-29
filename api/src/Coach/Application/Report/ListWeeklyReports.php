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
    /** A year of weeks. */
    public const int LIMIT = 52;

    public function __construct(public UserId $userId)
    {
    }
}
