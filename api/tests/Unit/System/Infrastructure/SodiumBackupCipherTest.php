<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Infrastructure;

use App\System\Application\Backup\BackupFailed;
use App\System\Infrastructure\Backup\SodiumBackupCipher;
use PHPUnit\Framework\TestCase;

final class SodiumBackupCipherTest extends TestCase
{
    private const string PASSPHRASE = 'test-only-passphrase-of-some-length-000';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->files, is_file(...)));
    }

    public function testADumpComesBackAsItWasAcrossSeveralChunks(): void
    {
        $dump = $this->file(random_bytes(200 * 1024 + 17));
        $encrypted = $this->file('');
        $decrypted = $this->file('');

        $cipher = new SodiumBackupCipher(self::PASSPHRASE);
        $cipher->encrypt($dump, $encrypted);
        $cipher->decrypt($encrypted, $decrypted);

        self::assertStringNotContainsString(substr((string) file_get_contents($dump), 0, 64), (string) file_get_contents($encrypted));
        self::assertSame(file_get_contents($dump), file_get_contents($decrypted));
    }

    public function testAnEmptyDumpToo(): void
    {
        [$encrypted, $decrypted] = [$this->file(''), $this->file('')];
        $cipher = new SodiumBackupCipher(self::PASSPHRASE);

        $cipher->encrypt($this->file(''), $encrypted);
        $cipher->decrypt($encrypted, $decrypted);

        self::assertSame('', file_get_contents($decrypted));
    }

    public function testAnotherPassphraseIsRefused(): void
    {
        $encrypted = $this->encrypted('the dump');

        $this->expectExceptionMessage('the passphrase is not the one it was made with');
        new SodiumBackupCipher('another-passphrase-of-some-length-00000')->decrypt($encrypted, $this->file(''));
    }

    public function testATamperedBackupIsRefused(): void
    {
        $encrypted = $this->encrypted(str_repeat('the dump ', 1000));
        $bytes = (string) file_get_contents($encrypted);
        $bytes[\strlen($bytes) - 20] = \chr(\ord($bytes[\strlen($bytes) - 20]) ^ 1);
        file_put_contents($encrypted, $bytes);

        $this->expectException(BackupFailed::class);
        new SodiumBackupCipher(self::PASSPHRASE)->decrypt($encrypted, $this->file(''));
    }

    public function testACutBackupIsRefused(): void
    {
        $dump = $this->file(random_bytes(100 * 1024));
        $encrypted = $this->file('');
        new SodiumBackupCipher(self::PASSPHRASE)->encrypt($dump, $encrypted);
        // Keep the header and the first chunk only: every chunk read is genuine, the end is missing.
        $bytes = (string) file_get_contents($encrypted);
        file_put_contents($encrypted, substr($bytes, 0, 5 + 16 + 8 + 24 + 4 + 64 * 1024 + 17));

        $this->expectExceptionMessage('the backup is cut short');
        new SodiumBackupCipher(self::PASSPHRASE)->decrypt($encrypted, $this->file(''));
    }

    public function testAnotherFileIsRefused(): void
    {
        $this->expectExceptionMessage('the backup is cut short');

        new SodiumBackupCipher(self::PASSPHRASE)->decrypt($this->file('PGDMP not encrypted'), $this->file(''));
    }

    public function testNoPassphraseNoBackup(): void
    {
        $this->expectExceptionMessage('BACKUP_PASSPHRASE is not set');

        new SodiumBackupCipher('')->encrypt($this->file('dump'), $this->file(''));
    }

    private function encrypted(string $contents): string
    {
        $encrypted = $this->file('');
        new SodiumBackupCipher(self::PASSPHRASE)->encrypt($this->file($contents), $encrypted);

        return $encrypted;
    }

    private function file(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cipher-test');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
