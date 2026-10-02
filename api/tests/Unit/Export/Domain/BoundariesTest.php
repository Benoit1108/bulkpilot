<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Domain;

use App\Export\Domain\ExportId;
use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportStatus;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

/**
 * The edges of an export request.
 */
final class BoundariesTest extends TestCase
{
    private const string ID = '00000000-0000-7000-8000-00000000e001';

    public function testAPeriodMayBeOpenOrASingleDay(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 10:00');

        self::assertNull(self::request(new \DateTimeImmutable('2026-09-01'), null, $now)->toDay(), 'from a day on');
        $one = self::request(new \DateTimeImmutable('2026-09-15 18:00'), new \DateTimeImmutable('2026-09-15 08:00'), $now);
        self::assertSame(['2026-09-15 00:00', '2026-09-15 00:00'], [$one->fromDay()?->format('Y-m-d H:i'), $one->toDay()?->format('Y-m-d H:i')], 'one day, whatever the hours');
        self::assertSame(['nutrition', 'training'], self::request(null, null, $now, ['training', 'nutrition', 'training'])->domains());
    }

    public function testOnlyAReadyExportBeforeItsTimeIsDownloadable(): void
    {
        self::assertTrue(ExportStatus::Processing->isOpen());
        self::assertFalse(ExportStatus::Ready->isOpen());
        self::assertFalse(self::request(null, null, new \DateTimeImmutable())->isDownloadable(new \DateTimeImmutable()), 'still queued');
    }

    /**
     * @param list<string> $domains
     */
    private static function request(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, \DateTimeImmutable $now, array $domains = []): ExportRequest
    {
        return ExportRequest::request(new ExportId(self::ID), new UserId(self::ID), $from, $to, $domains, $now);
    }
}
