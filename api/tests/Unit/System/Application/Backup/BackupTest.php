<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Application\Backup;

use App\Shared\Domain\Identifier\UserId;
use App\System\Application\Backup\BackupErasureLog;
use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\Backups;
use App\System\Application\Backup\CreateBackup;
use App\System\Application\Backup\RunScheduledBackups;
use App\System\Application\Backup\VerifyBackup;
use App\System\Application\Monitoring\Heartbeats;
use App\System\Domain\Backup\Retention;
use App\Tests\Support\System\FakeDatabaseDump;
use App\Tests\Support\System\InMemoryBackupStore;
use App\Tests\Support\System\InMemoryBeats;
use App\Tests\Support\System\RecordingLogger;
use App\Tests\Support\System\RecordingPings;
use App\Tests\Support\System\ReversingBackupCipher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class BackupTest extends TestCase
{
    private const string ERASED = '0199a0b1-7c3e-7d4a-9b2f-0000000000e1';

    private FakeDatabaseDump $database;
    private InMemoryBackupStore $store;
    private RecordingLogger $logger;
    private MockClock $clock;
    private InMemoryBeats $beats;
    private RecordingPings $pings;

    protected function setUp(): void
    {
        $this->database = new FakeDatabaseDump();
        $this->store = new InMemoryBackupStore();
        $this->logger = new RecordingLogger();
        $this->clock = new MockClock(new \DateTimeImmutable('2026-09-30 02:30:00+00:00'));
        $this->beats = new InMemoryBeats();
        $this->pings = new RecordingPings();
    }

    public function testEachOutcomeIsToldToTheMonitoring(): void
    {
        $this->backups()->create();
        $this->backups()->verify();

        self::assertSame(['backup' => '2026-09-30T02:30:00+00:00', 'backup_verify' => '2026-09-30T02:30:00+00:00'], array_map(static fn (\DateTimeImmutable $at): string => $at->format(\DATE_ATOM), $this->beats->last));
        self::assertSame(['backup up', 'backup_verify up'], $this->pings->sent);

        $this->database->failing = true;
        try {
            $this->backups()->create();
            self::fail('The backup should have failed.');
        } catch (BackupFailed) {
        }
        self::assertSame('backup down: pg_dump failed: connection refused', $this->pings->last());
        // A failure is no beat: the last one stays, and grows old.
        self::assertCount(2, $this->beats->last);
    }

    public function testABackupIsSentEncryptedUnderItsDateAndOldOnesAreDeleted(): void
    {
        $this->store->objects = [
            'db/2026/08/01/023000.dump.enc' => 'old',
            'db/2026/09/29/023000.dump.enc' => 'yesterday',
            'db/README' => 'not a backup, left alone',
        ];

        $created = $this->backups()->create();

        self::assertSame('db/2026/09/30/023000.dump.enc', $created->key);
        self::assertSame(1, $created->deleted);
        self::assertSame(strrev(FakeDatabaseDump::DUMP), $this->store->objects['db/2026/09/30/023000.dump.enc']);
        self::assertSame(['db/2026/09/29/023000.dump.enc', 'db/README', 'db/2026/09/30/023000.dump.enc'], array_keys($this->store->objects));
        self::assertSame(['info system.backup_created'], $this->logger->lines());
    }

    public function testAFailedDumpSendsNothingAndIsLoggedAsAnError(): void
    {
        $this->database->failing = true;

        try {
            $this->backups()->create();
            self::fail('The backup should have failed.');
        } catch (BackupFailed $failure) {
            self::assertSame('pg_dump failed: connection refused', $failure->getMessage());
        }
        self::assertSame([], $this->store->objects);
        self::assertSame(['error system.backup_failed'], $this->logger->lines());
    }

    public function testAFailedUploadKeepsTheOldBackups(): void
    {
        $this->store->objects = ['db/2026/08/01/023000.dump.enc' => 'old', 'db/2026/09/29/023000.dump.enc' => 'yesterday'];
        $this->store->failingPuts = true;

        try {
            $this->backups()->create();
            self::fail('The backup should have failed.');
        } catch (BackupFailed) {
        }
        self::assertArrayHasKey('db/2026/08/01/023000.dump.enc', $this->store->objects, 'nothing is deleted when nothing new was kept');
        self::assertCount(2, $this->store->objects);
        self::assertSame(['error system.backup_failed'], $this->logger->lines());
    }

    public function testAnUnreachableBucketFailsTheBackup(): void
    {
        $this->store->failing = true;

        $this->expectException(BackupFailed::class);
        $this->backups()->create();
    }

    public function testTheLatestBackupIsRestoredAndChecked(): void
    {
        $this->backups()->create();
        $this->clock->modify('+2 hours');

        $verified = $this->backups()->verify();

        self::assertSame('db/2026/09/30/023000.dump.enc', $verified->key);
        self::assertSame(31, $verified->check->tables);
        self::assertSame(2, $verified->check->accounts());
        self::assertSame([FakeDatabaseDump::DUMP], $this->database->restored);
        self::assertContains('info system.backup_verified', $this->logger->lines());
    }

    public function testTheCheckCountsTheErasedAccountsARestoreWouldBringBack(): void
    {
        $log = new BackupErasureLog($this->store, true);
        $log->remember(new UserId(self::ERASED), new \DateTimeImmutable('2026-09-29 04:00:00+00:00'));
        $this->database->restoredAccounts = [self::ERASED];
        $this->backups()->create();

        self::assertSame(1, $this->backups()->verify()->check->erasedToReapply);
        self::assertSame([self::ERASED], array_map(static fn (UserId $id): string => $id->value, $log->all()));
    }

    public function testErasureMarkersGoOnceNoBackupCanHoldTheAccount(): void
    {
        $log = new BackupErasureLog($this->store, true);
        $log->remember(new UserId(self::ERASED), new \DateTimeImmutable('2026-08-20 04:00:00+00:00'));
        $log->remember(new UserId('0199a0b1-7c3e-7d4a-9b2f-0000000000e2'), new \DateTimeImmutable('2026-08-25 04:00:00+00:00'));

        $this->backups()->create();

        self::assertSame(['0199a0b1-7c3e-7d4a-9b2f-0000000000e2'], array_map(static fn (UserId $id): string => $id->value, $log->all()), 'past the 30 days of backups and the 7 extra ones');
    }

    public function testNothingIsRememberedWhileBackupsAreOff(): void
    {
        $log = new BackupErasureLog($this->store, false);
        $log->remember(new UserId(self::ERASED), $this->clock->now());

        self::assertSame([], $this->store->objects);
        self::assertSame([], $log->all());
    }

    public function testNoBackupFailsTheVerification(): void
    {
        $this->expectExceptionMessage('there is no backup to verify');

        $this->backups()->verify();
    }

    public function testAStaleLatestBackupMeansBackupsStopped(): void
    {
        $this->backups()->create();
        $this->clock->modify(\sprintf('+%d hours', Backups::MAX_AGE_HOURS + 1));

        try {
            $this->backups()->verify();
            self::fail('The verification should have failed.');
        } catch (BackupFailed $failure) {
            self::assertStringContainsString('older than 48 hours', $failure->getMessage());
        }
        self::assertSame([], $this->database->restored);
        self::assertContains('error system.backup_verification_failed', $this->logger->lines());
    }

    public function testABackupThatDoesNotRestoreFailsTheVerification(): void
    {
        $this->store->objects['db/2026/09/30/010000.dump.enc'] = 'garbage';

        $this->expectExceptionMessage('pg_restore failed');
        $this->backups()->verify();
    }

    public function testTheLatestBackupIsFetchedDecryptedForARestoreByHand(): void
    {
        $this->store->objects['db/2026/09/29/023000.dump.enc'] = strrev('older dump');
        $this->backups()->create();
        $path = (string) tempnam(sys_get_temp_dir(), 'fetched');

        $latest = $this->backups()->fetch($path);
        $contents = (string) file_get_contents($path);
        $older = $this->backups()->fetch($path, 'db/2026/09/29/023000.dump.enc');

        self::assertSame('db/2026/09/30/023000.dump.enc', $latest);
        self::assertSame(FakeDatabaseDump::DUMP, $contents);
        self::assertSame('db/2026/09/29/023000.dump.enc', $older);
        self::assertSame('older dump', file_get_contents($path));
        unlink($path);
    }

    public function testAnUnknownBackupIsNotFetched(): void
    {
        $this->expectExceptionMessage('there is no backup db/2026/01/01/000000.dump.enc');

        $this->backups()->fetch('/tmp/nowhere', 'db/2026/01/01/000000.dump.enc');
    }

    public function testTheScheduleRunsTheBackupAndItsTestRestore(): void
    {
        $run = new RunScheduledBackups($this->backups());

        $run(new CreateBackup());
        $run(new VerifyBackup());

        self::assertSame(['info system.backup_created', 'info system.backup_verified'], $this->logger->lines());
    }

    private function backups(): Backups
    {
        return new Backups($this->database, new ReversingBackupCipher(), $this->store, new Retention(30), $this->clock, $this->logger, new Heartbeats($this->beats, $this->pings, $this->clock, new RecordingLogger()));
    }
}
