<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Backup;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\BackupStore;

/**
 * No backup bucket (development): every use says so instead of failing somewhere in the SDK.
 */
final readonly class UnconfiguredBackupStore implements BackupStore
{
    public function put(string $key, string $path): void
    {
        throw self::failure();
    }

    public function keys(string $prefix): array
    {
        throw self::failure();
    }

    public function fetch(string $key, string $path): void
    {
        throw self::failure();
    }

    public function delete(string $key): void
    {
        throw self::failure();
    }

    private static function failure(): BackupFailed
    {
        return BackupFailed::because('no backup bucket is configured (BACKUP_S3_BUCKET)');
    }
}
