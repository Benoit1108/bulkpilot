<?php

declare(strict_types=1);

namespace App\Coach\Application\Report;

use App\Coach\Domain\Analysis\Adherence;
use App\Coach\Domain\Analysis\Pace;
use App\Coach\Domain\Report\WeekFigures;
use App\Coach\Domain\Report\WeeklyReport;

final readonly class WeeklyReportView
{
    public function __construct(
        public \DateTimeImmutable $weekStart,
        public WeekFigures $figures,
        public Adherence $adherence,
        public Pace $verdict,
        public ?string $action,
    ) {
    }

    public static function of(WeeklyReport $report): self
    {
        return new self($report->weekStart(), $report->figures(), $report->adherence(), $report->verdict(), $report->action());
    }
}
