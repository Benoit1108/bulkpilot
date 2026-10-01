<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Exception\ExportNotReady;
use App\Export\Domain\Exception\InvalidExport;
use App\Export\Domain\ExportExpired;
use App\Export\Domain\ExportId;
use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportStatus;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

final class ExportRequestTest extends TestCase
{
    private const string ARCHIVE = '00000000-0000-7000-8000-00000000a001';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-09-30 10:00:00');
    }

    /**
     * @param list<string> $domains
     */
    private function export(?string $from = null, ?string $to = null, array $domains = []): ExportRequest
    {
        return ExportRequest::request(
            new ExportId('00000000-0000-7000-8000-00000000e001'),
            new UserId('00000000-0000-7000-8000-00000000a1e7'),
            null === $from ? null : new \DateTimeImmutable($from.' 15:00'),
            null === $to ? null : new \DateTimeImmutable($to),
            $domains,
            $this->now,
        );
    }

    public function testAnExportIsAskedForAPeriodAndDomains(): void
    {
        $export = $this->export('2026-09-01', '2026-09-30', ['training', 'nutrition', 'training']);

        self::assertSame(ExportStatus::Queued, $export->status($this->now));
        self::assertSame('2026-09-01 00:00', $export->fromDay()?->format('Y-m-d H:i'));
        self::assertSame(['nutrition', 'training'], $export->domains());
        self::assertFalse($export->isDownloadable($this->now));

        $this->expectException(InvalidExport::class);
        $this->export('2026-09-30', '2026-09-01');
    }

    public function testAReadyExportIsKeptADay(): void
    {
        $export = $this->export();
        self::assertTrue($export->start());
        self::assertFalse($export->start(), 'taken once: a redelivered message leaves it');
        $export->ready(new MediaId(self::ARCHIVE), $this->now);

        self::assertSame('2026-10-01 10:00', $export->expiresAt()?->format('Y-m-d H:i'));
        self::assertSame(self::ARCHIVE, $export->archive($this->now->modify('+23 hours'))->value);
        self::assertSame(ExportStatus::Expired, $export->status($this->now->modify('+24 hours')), 'past its day, before the purge');

        $this->expectException(ExportNotReady::class);
        $export->archive($this->now->modify('+24 hours'));
    }

    public function testAQueuedOrFailedExportCannotBeDownloaded(): void
    {
        $export = $this->export();
        $export->fail();

        self::assertSame(ExportStatus::Failed, $export->status($this->now));
        $this->expectException(ExportNotReady::class);
        $export->archive($this->now);
    }

    public function testExpiringDeletesTheArchiveOfAReadyExportOnly(): void
    {
        $queued = $this->export();
        $queued->expire($this->now);
        self::assertSame([], $queued->pullDomainEvents());

        $ready = $this->export();
        $ready->start();
        $ready->ready(new MediaId(self::ARCHIVE), $this->now);
        $ready->expire($this->now->modify('+1 day'));
        $ready->expire($this->now->modify('+2 days'));
        $events = $ready->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ExportExpired::class, $events[0]);
        self::assertSame(self::ARCHIVE, $events[0]->archiveId->value);
        self::assertSame(ExportStatus::Expired, $ready->status($this->now));
    }

    public function testAScopeCoversItsDomainsAndDays(): void
    {
        $all = new ExportScope();
        self::assertTrue($all->covers('coach'));
        self::assertTrue($all->includes(new \DateTimeImmutable('2001-01-01')));

        $scope = new ExportScope(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-30'), ['coach']);
        self::assertTrue($scope->covers('coach'));
        self::assertFalse($scope->covers('nutrition'));
        self::assertTrue($scope->includes(new \DateTimeImmutable('2026-09-30 23:59')));
        self::assertFalse($scope->includes(new \DateTimeImmutable('2026-08-31 23:59')));
        self::assertFalse($scope->includes(new \DateTimeImmutable('2026-10-01')));
    }
}
