<?php

declare(strict_types=1);

namespace App\Coach\Domain\Report;

use App\Shared\Domain\Identifier\UserId;

interface WeeklyReportRepository
{
    public function save(WeeklyReport $report): void;

    public function has(UserId $userId, \DateTimeImmutable $weekStart): bool;

    /**
     * @return list<WeeklyReport> the latest week first
     */
    public function latestOf(UserId $userId, int $limit): array;
}
