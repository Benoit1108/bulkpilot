<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

/**
 * The application's database, dumped and restored by PostgreSQL's own tools (ADR-0031).
 */
interface DatabaseDump
{
    /**
     * @param string $path where the dump is written (custom format)
     *
     * @throws BackupFailed
     */
    public function dump(string $path): void;

    /** The key tables a test restore counts (an empty one is reported, not refused). */
    public const array KEY_TABLES = ['users', 'journal_entries', 'workouts', 'progression_weigh_ins', 'media_files', 'audit_events'];

    /**
     * Restores a dump into a throwaway database, checks it against the live one, then drops it.
     *
     * @param list<string> $erasedIds accounts erased since: counted when the restore has them
     *
     * @throws BackupFailed the restore failed or the restored database is not the live one's
     */
    public function restoreAndCheck(string $path, array $erasedIds): RestoreCheck;
}
