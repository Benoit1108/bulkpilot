<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

use App\Shared\Application\File\TemporaryFile;
use App\System\Application\Monitoring\Heartbeats;
use App\System\Domain\Backup\BackupKey;
use App\System\Domain\Backup\ErasureMarker;
use App\System\Domain\Backup\Retention;
use App\System\Domain\Monitoring\Beat;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * The database's backups (spec §106, ADR-0031): dumped, encrypted and sent away every night, the
 * past ones deleted after the retention (never the latest), and a test restore every week. Every
 * failure is logged as an error and thrown, and each outcome is told to the monitoring (a heartbeat
 * when it went well, docs/MONITORING.md): nothing half-done is quiet.
 */
final readonly class Backups
{
    /** Backups are nightly: an older latest one means they stopped. */
    public const int MAX_AGE_HOURS = 48;
    private const string TEMPORARY = 'bulkpilot-backup-';

    public function __construct(
        private DatabaseDump $database,
        private BackupCipher $cipher,
        private BackupStore $store,
        private Retention $retention,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private Heartbeats $heartbeats,
    ) {
    }

    /**
     * @throws BackupFailed
     */
    public function create(): BackupCreated
    {
        $now = $this->clock->now();
        $key = BackupKey::at($now);
        // A run killed half-way (out of memory, restart) left a plain dump behind: not for long.
        TemporaryFile::removeStale(self::TEMPORARY, $now);
        $dump = TemporaryFile::create(self::TEMPORARY);
        $encrypted = TemporaryFile::create(self::TEMPORARY);
        try {
            $this->database->dump($dump);
            $this->cipher->encrypt($dump, $encrypted);
            $this->store->put($key->value, $encrypted);
            $deleted = $this->deleteExpired($now);
        } catch (BackupFailed|\RuntimeException $failure) {
            $this->logger->error('system.backup_failed', ['key' => $key->value, 'reason' => $failure->getMessage()]);
            $this->heartbeats->failed(Beat::Backup, $failure->getMessage());

            throw $failure instanceof BackupFailed ? $failure : BackupFailed::because($failure->getMessage(), $failure);
        } finally {
            TemporaryFile::remove($dump, $encrypted);
        }
        $this->logger->info('system.backup_created', ['key' => $key->value, 'deleted' => $deleted]);
        $this->heartbeats->beat(Beat::Backup);

        return new BackupCreated($key->value, $deleted);
    }

    /**
     * The latest backup must be recent and restore into a database like the live one.
     *
     * @throws BackupFailed
     */
    public function verify(): VerifiedBackup
    {
        $key = null;
        try {
            $latest = Retention::latest($this->backups()) ?? throw BackupFailed::because('there is no backup to verify');
            $key = $latest->value;
            if ($latest->takenAt < $this->clock->now()->modify(\sprintf('-%d hours', self::MAX_AGE_HOURS))) {
                throw BackupFailed::because(\sprintf('the latest backup (%s) is older than %d hours', $latest->value, self::MAX_AGE_HOURS));
            }
            $check = $this->restore($latest);
        } catch (BackupFailed $failure) {
            $this->logger->error('system.backup_verification_failed', ['key' => $key, 'reason' => $failure->getMessage()]);
            $this->heartbeats->failed(Beat::BackupVerify, $failure->getMessage());

            throw $failure;
        }
        $this->logger->info('system.backup_verified', [
            'key' => $latest->value,
            'tables' => $check->tables,
            'migration' => $check->migration,
            'rows' => $check->rows,
            'erased_to_reapply' => $check->erasedToReapply,
        ]);
        $this->heartbeats->beat(Beat::BackupVerify);

        return new VerifiedBackup($latest->value, $check);
    }

    /**
     * Downloads a backup (the latest by default) and decrypts it, for a restore by hand.
     *
     * @return string the backup's key
     *
     * @throws BackupFailed
     */
    public function fetch(string $path, ?string $key = null): string
    {
        $backups = $this->backups();
        if (null === $key) {
            $key = Retention::latest($backups)->value ?? throw BackupFailed::because('there is no backup');
        } elseif (!\in_array($key, array_map(static fn (BackupKey $backup): string => $backup->value, $backups), true)) {
            throw BackupFailed::because(\sprintf('there is no backup %s', $key));
        }
        $encrypted = TemporaryFile::create(self::TEMPORARY);
        try {
            $this->store->fetch($key, $encrypted);
            $this->cipher->decrypt($encrypted, $path);
        } finally {
            TemporaryFile::remove($encrypted);
        }

        return $key;
    }

    /**
     * @return list<BackupKey>
     */
    private function backups(): array
    {
        return array_values(array_filter(array_map(BackupKey::parse(...), $this->store->keys(BackupKey::PREFIX))));
    }

    /**
     * @return list<string> the accounts erased since the oldest backup kept
     */
    private function erased(): array
    {
        return array_values(array_map(static fn (ErasureMarker $marker): string => $marker->userId, array_filter(array_map(ErasureMarker::parse(...), $this->store->keys(ErasureMarker::PREFIX)))));
    }

    private function deleteExpired(\DateTimeImmutable $now): int
    {
        $expired = $this->retention->expired($this->backups(), $now);
        foreach ($expired as $backup) {
            $this->store->delete($backup->value);
        }
        foreach (array_filter(array_map(ErasureMarker::parse(...), $this->store->keys(ErasureMarker::PREFIX))) as $marker) {
            if ($marker->isObsolete($this->retention, $now)) {
                $this->store->delete($marker->value);
            }
        }

        return \count($expired);
    }

    private function restore(BackupKey $backup): RestoreCheck
    {
        $encrypted = TemporaryFile::create(self::TEMPORARY);
        $dump = TemporaryFile::create(self::TEMPORARY);
        try {
            $this->store->fetch($backup->value, $encrypted);
            $this->cipher->decrypt($encrypted, $dump);

            return $this->database->restoreAndCheck($dump, $this->erased());
        } finally {
            TemporaryFile::remove($encrypted, $dump);
        }
    }
}
