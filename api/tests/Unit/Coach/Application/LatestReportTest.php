<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coach\Application;

use App\Coach\Application\Notifying\LatestReport;
use App\Tests\Support\Coach\CoachTestCase;

final class LatestReportTest extends CoachTestCase
{
    public function testTheLatestReportIsTheWeekItCovers(): void
    {
        $latest = new LatestReport($this->reports);
        self::assertNull($latest->latestWeek($this->alex));

        $this->stalledGain();
        $this->analyse();

        self::assertSame('2026-09-21', $latest->latestWeek($this->alex)?->format('Y-m-d'));
        self::assertNull($latest->latestWeek($this->sam));
    }
}
