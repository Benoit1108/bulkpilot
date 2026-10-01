<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Domain;

use App\System\Domain\Backup\BackupKey;
use App\System\Domain\Backup\Retention;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackupKeyTest extends TestCase
{
    public function testAKeyNamesTheUtcTimeItWasTaken(): void
    {
        $key = BackupKey::at(new \DateTimeImmutable('2026-09-30 04:30:05', new \DateTimeZone('Europe/Paris')));

        self::assertSame('db/2026/09/30/023005.dump.enc', $key->value);
        self::assertSame('2026-09-30T02:30:05+00:00', $key->takenAt->format(\DATE_ATOM));
    }

    public function testAKeyIsReadBack(): void
    {
        $key = BackupKey::parse('db/2026/09/30/023005.dump.enc');

        self::assertSame('2026-09-30T02:30:05+00:00', $key?->takenAt->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignKeys(): iterable
    {
        yield 'another file' => ['db/notes.txt'];
        yield 'another prefix' => ['photos/2026/09/30/023005.dump.enc'];
        yield 'not a date' => ['db/2026/13/45/256199.dump.enc'];
        yield 'not encrypted' => ['db/2026/09/30/023005.dump'];
    }

    #[DataProvider('foreignKeys')]
    public function testAnythingElseUnderThePrefixIsNotABackup(string $key): void
    {
        self::assertNull(BackupKey::parse($key));
    }

    public function testBackupsOlderThanTheRetentionAreDeleted(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 02:30:00+00:00');
        $keys = array_map(
            static fn (string $day): BackupKey => BackupKey::at(new \DateTimeImmutable($day.' 02:30:00+00:00')),
            ['2026-08-30', '2026-08-31', '2026-09-01', '2026-09-29'],
        );

        $expired = new Retention(30)->expired($keys, $now);

        // Exactly 30 days old (2026-08-31 02:30) is still kept.
        self::assertSame(['db/2026/08/30/023000.dump.enc'], array_map(static fn (BackupKey $key): string => $key->value, $expired));
    }

    public function testTheLatestBackupIsKeptEvenWhenTooOld(): void
    {
        $old = [BackupKey::at(new \DateTimeImmutable('2026-01-01 02:30:00+00:00')), BackupKey::at(new \DateTimeImmutable('2026-01-02 02:30:00+00:00'))];

        $expired = new Retention(30)->expired($old, new \DateTimeImmutable('2026-09-30 02:30:00+00:00'));

        self::assertSame(['db/2026/01/01/023000.dump.enc'], array_map(static fn (BackupKey $key): string => $key->value, $expired));
        self::assertSame('db/2026/01/02/023000.dump.enc', Retention::latest($old)?->value);
        self::assertNull(Retention::latest([]));
    }

    public function testBackupsAreKeptADayAtLeast(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Retention(0);
    }
}
