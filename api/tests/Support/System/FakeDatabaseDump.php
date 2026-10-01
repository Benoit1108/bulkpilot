<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use App\System\Application\Backup\BackupFailed;
use App\System\Application\Backup\DatabaseDump;
use App\System\Application\Backup\RestoreCheck;

/**
 * Dumps a fixed text; a restore reads it back and checks it is that text.
 */
final class FakeDatabaseDump implements DatabaseDump
{
    public const string DUMP = 'PGDMP fake dump';

    public bool $failing = false;
    /** @var list<string> */
    public array $restored = [];

    public function dump(string $path): void
    {
        if ($this->failing) {
            throw BackupFailed::because('pg_dump failed: connection refused');
        }
        file_put_contents($path, self::DUMP);
    }

    /** @var list<string> the erased accounts the restored database still has */
    public array $restoredAccounts = [];

    public function restoreAndCheck(string $path, array $erasedIds): RestoreCheck
    {
        $contents = (string) file_get_contents($path);
        $this->restored[] = $contents;
        if (self::DUMP !== $contents) {
            throw BackupFailed::because('pg_restore failed: not a dump');
        }

        return new RestoreCheck(31, 'DoctrineMigrations\Version20260930093444', ['users' => 2, 'workouts' => 5], \count(array_intersect($erasedIds, $this->restoredAccounts)));
    }
}
