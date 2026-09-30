<?php

declare(strict_types=1);

namespace App\Coach\Application\Notifying;

use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Shared\Application\Notifying\CoachReports;
use App\Shared\Domain\Identifier\UserId;

/**
 * The latest weekly report, for its notification (ADR-0029).
 */
final readonly class LatestReport implements CoachReports
{
    public function __construct(private WeeklyReportRepository $reports)
    {
    }

    public function latestWeek(UserId $userId): ?\DateTimeImmutable
    {
        return ($this->reports->latestOf($userId, 1)[0] ?? null)?->weekStart();
    }
}
