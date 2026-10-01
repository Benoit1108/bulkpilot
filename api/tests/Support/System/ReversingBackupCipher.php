<?php

declare(strict_types=1);

namespace App\Tests\Support\System;

use App\System\Application\Backup\BackupCipher;

/**
 * Stands for the encryption in handler tests: what is stored is not the dump as it is.
 */
final class ReversingBackupCipher implements BackupCipher
{
    public function encrypt(string $from, string $to): void
    {
        file_put_contents($to, strrev((string) file_get_contents($from)));
    }

    public function decrypt(string $from, string $to): void
    {
        file_put_contents($to, strrev((string) file_get_contents($from)));
    }
}
