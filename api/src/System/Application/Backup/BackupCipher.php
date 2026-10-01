<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

/**
 * Backups leave the server encrypted (and authenticated: a tampered or cut file is refused).
 */
interface BackupCipher
{
    /** @throws BackupFailed */
    public function encrypt(string $from, string $to): void;

    /** @throws BackupFailed also when the passphrase is not the one it was encrypted with */
    public function decrypt(string $from, string $to): void;
}
