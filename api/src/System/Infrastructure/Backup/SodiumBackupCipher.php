<?php

declare(strict_types=1);

namespace App\System\Infrastructure\Backup;

use App\System\Application\Backup\BackupCipher;
use App\System\Application\Backup\BackupFailed;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * libsodium's secret stream (XChaCha20-Poly1305), a key derived from the passphrase with Argon2id:
 * every chunk is authenticated and the last one marked, so that a tampered, reordered or cut
 * backup is refused rather than restored. The salt and cost travel in the file's header.
 */
final readonly class SodiumBackupCipher implements BackupCipher
{
    private const string MAGIC = "BPBK\x01";
    private const int CHUNK = 64 * 1024;
    /** A long random passphrase is required in production (docs/DEPLOYMENT.md): interactive cost suffices. */
    private const int OPS = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
    private const int MEMORY = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

    public function __construct(#[Autowire('%env(BACKUP_PASSPHRASE)%')] private string $passphrase)
    {
    }

    public function encrypt(string $from, string $to): void
    {
        [$in, $out] = $this->open($from, $to);
        try {
            $salt = random_bytes(\SODIUM_CRYPTO_PWHASH_SALTBYTES);
            [$state, $header] = self::strings(sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key($salt, self::OPS, self::MEMORY)));
            self::write($out, self::MAGIC.$salt.pack('NN', self::OPS, self::MEMORY).$header);
            do {
                $chunk = fread($in, self::CHUNK);
                if (false === $chunk) {
                    throw BackupFailed::because('the dump could not be read');
                }
                $last = feof($in);
                $sealed = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $last ? \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                self::write($out, pack('N', \strlen($sealed)).$sealed);
            } while (!$last);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decrypt(string $from, string $to): void
    {
        [$in, $out] = $this->open($from, $to);
        try {
            $head = self::read($in, \strlen(self::MAGIC) + \SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (!str_starts_with($head, self::MAGIC)) {
                throw BackupFailed::because('this is not a backup of this application');
            }
            $offset = \strlen(self::MAGIC);
            $salt = substr($head, $offset, \SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $cost = unpack('Nops/Nmemory', substr($head, $offset + \SODIUM_CRYPTO_PWHASH_SALTBYTES, 8));
            if (!\is_array($cost) || !\is_int($cost['ops'] ?? null) || !\is_int($cost['memory'] ?? null)) {
                throw BackupFailed::because('the backup header is damaged');
            }
            // A forged header must not make the key derivation eat the worker's memory or time.
            if ($cost['ops'] < self::OPS || $cost['ops'] > \SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE
                || $cost['memory'] < self::MEMORY || $cost['memory'] > \SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE) {
                throw BackupFailed::because('the backup header asks for an unexpected cost');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($head, $offset + \SODIUM_CRYPTO_PWHASH_SALTBYTES + 8), $this->key($salt, $cost['ops'], $cost['memory']));
            do {
                $length = unpack('N', self::read($in, 4));
                $length = \is_array($length) && \is_int($length[1] ?? null) ? $length[1] : 0;
                if ($length < \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > self::CHUNK + \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw BackupFailed::because('the backup is damaged (a chunk has an impossible length)');
                }
                $sealed = self::read($in, $length);
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $sealed);
                if (false === $opened) {
                    throw BackupFailed::because('the backup is damaged, or the passphrase is not the one it was made with');
                }
                $chunk = $opened[0];
                $tag = $opened[1];
                self::write($out, \is_string($chunk) ? $chunk : throw BackupFailed::because('the backup is damaged'));
            } while (\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL !== $tag);
            if ('' !== fread($in, 1)) {
                throw BackupFailed::because('the backup has data after its end');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * @return array{resource, resource}
     */
    private function open(string $from, string $to): array
    {
        if ('' === $this->passphrase) {
            throw BackupFailed::because('BACKUP_PASSPHRASE is not set');
        }
        $in = @fopen($from, 'r');
        $out = @fopen($to, 'w');
        if (false === $in || false === $out) {
            throw BackupFailed::because('the backup files could not be opened');
        }

        return [$in, $out];
    }

    private function key(string $salt, int $ops, int $memory): string
    {
        return sodium_crypto_pwhash(\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $this->passphrase, $salt, $ops, $memory, \SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    /**
     * @param array<mixed> $pair
     *
     * @return array{string, string}
     */
    private static function strings(array $pair): array
    {
        [$first, $second] = [$pair[0] ?? null, $pair[1] ?? null];
        if (!\is_string($first) || !\is_string($second)) {
            throw BackupFailed::because('the encryption could not start');
        }

        return [$first, $second];
    }

    /**
     * @param resource $in
     */
    private static function read($in, int $length): string
    {
        $data = $length > 0 ? fread($in, $length) : '';
        if (false === $data || \strlen($data) !== $length) {
            throw BackupFailed::because('the backup is cut short');
        }

        return $data;
    }

    /**
     * @param resource $out
     */
    private static function write($out, string $data): void
    {
        if (false === fwrite($out, $data)) {
            throw BackupFailed::because('the backup could not be written');
        }
    }
}
