<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Coach\Domain\Report\WeeklyReport;
use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryWeeklyReportRepository implements WeeklyReportRepository
{
    /** @var array<string, WeeklyReport> by owner and week */
    public array $reports = [];

    public function save(WeeklyReport $report): void
    {
        $this->reports[self::key($report->ownerId(), $report->weekStart())] = $report;
    }

    public function has(UserId $userId, \DateTimeImmutable $weekStart): bool
    {
        return isset($this->reports[self::key($userId, $weekStart)]);
    }

    public function latestOf(UserId $userId, int $limit): array
    {
        $reports = array_values(array_filter($this->reports, static fn (WeeklyReport $report): bool => $report->ownerId()->equals($userId)));
        usort($reports, static fn (WeeklyReport $a, WeeklyReport $b): int => $b->weekStart() <=> $a->weekStart());

        return \array_slice($reports, 0, $limit);
    }

    private static function key(UserId $userId, \DateTimeImmutable $weekStart): string
    {
        return $userId->value.'|'.$weekStart->format('Y-m-d');
    }
}
