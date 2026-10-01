<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

/**
 * Where backups are kept, away from the server (a bucket distinct from the photos').
 */
interface BackupStore
{
    /** @throws BackupFailed */
    public function put(string $key, string $path): void;

    /**
     * @return list<string> every key under the prefix
     *
     * @throws BackupFailed
     */
    public function keys(string $prefix): array;

    /** @throws BackupFailed */
    public function fetch(string $key, string $path): void;

    /** @throws BackupFailed */
    public function delete(string $key): void;
}
