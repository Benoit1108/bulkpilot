<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Presentation;

use App\System\Application\Health\HealthReport;
use App\System\Presentation\Api\Health\Health;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testAHealthyReportIsOk(): void
    {
        $health = Health::fromReport(new HealthReport(['database' => true]), false);

        self::assertSame('ok', $health->status);
        self::assertSame(['database' => 'up'], $health->checks);
    }

    public function testADownCheckIsReportedDegraded(): void
    {
        $health = Health::fromReport(new HealthReport(['database' => false, 'storage' => true]), false);

        self::assertSame('degraded', $health->status);
        self::assertSame(['database' => 'down', 'storage' => 'up'], $health->checks);
    }
}
