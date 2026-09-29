<?php

declare(strict_types=1);

namespace App\Coach\Application\Report;

use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListWeeklyReportsHandler implements QueryHandler
{
    public function __construct(private WeeklyReportRepository $reports)
    {
    }

    /**
     * @return list<WeeklyReportView>
     */
    public function __invoke(ListWeeklyReports $query): array
    {
        return array_map(WeeklyReportView::of(...), $this->reports->latestOf($query->userId, ListWeeklyReports::LIMIT));
    }
}
