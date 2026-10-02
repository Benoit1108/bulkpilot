<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Domain;

use App\System\Domain\Backup\BackupKey;
use App\System\Domain\Backup\ErasureMarker;
use App\System\Domain\Backup\Retention;
use PHPUnit\Framework\TestCase;

/**
 * The edges of backup retention and erasure markers.
 */
final class BoundariesTest extends TestCase
{
    private const string USER = '0199a0b1-0000-7000-8000-000000000001';

    public function testAMarkerIsForTheUtcDayOfTheErasure(): void
    {
        $marker = ErasureMarker::of(self::USER, new \DateTimeImmutable('2026-09-30 01:30', new \DateTimeZone('Europe/Paris')));

        self::assertSame('erasures/2026-09-29/'.self::USER, $marker->value);
        self::assertSame('2026-09-29 00:00', $marker->erasedOn->format('Y-m-d H:i'));
        self::assertNull(ErasureMarker::parse('erasures/2026-02-30/'.self::USER), 'no such day');
        self::assertSame(self::USER, ErasureMarker::parse('erasures/2026-02-28/'.self::USER)?->userId);
    }

    public function testAMarkerOutlivesTheBackupsByAWeek(): void
    {
        $marker = ErasureMarker::of(self::USER, new \DateTimeImmutable('2026-09-01 00:00'));
        $retention = new Retention(30);

        self::assertFalse($marker->isObsolete($retention, new \DateTimeImmutable('2026-10-08 00:00')), 'exactly 37 days: still kept');
        self::assertTrue($marker->isObsolete($retention, new \DateTimeImmutable('2026-10-08 00:01')));
    }

    public function testADayOfRetentionIsTheLeastAndTheLatestBackupIsNeverExpired(): void
    {
        self::assertSame(1, new Retention(1)->days);
        $old = BackupKey::at(new \DateTimeImmutable('2026-08-01 02:30'));
        $older = BackupKey::at(new \DateTimeImmutable('2026-07-01 02:30'));

        self::assertSame([$older->value], array_map(static fn (BackupKey $key): string => $key->value, new Retention(30)->expired([$old, $older], new \DateTimeImmutable('2026-09-30'))), 'the latest stays, however old');
        self::assertSame([], new Retention(30)->expired([], new \DateTimeImmutable('2026-09-30')));
        self::assertSame($old->value, Retention::latest([$older, $old, BackupKey::at(new \DateTimeImmutable('2026-08-01 02:30'))])?->value);
    }
}
