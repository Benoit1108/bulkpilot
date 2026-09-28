<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Domain\User\EncryptedSecret;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * XSalsa20-Poly1305 (libsodium secretbox) with a sub-key derived from APP_ENCRYPTION_KEY, random nonce per value. Stored as "v1:<base64(nonce|box)>"
 * so that the key or algorithm can be rotated later.
 */
final readonly class SodiumSecretCipher implements SecretCipher
{
    private const string VERSION = 'v1:';

    private string $key;

    public function __construct(
        #[Autowire(env: 'base64:APP_ENCRYPTION_KEY')]
        #[\SensitiveParameter]
        string $masterKey,
    ) {
        if (\SODIUM_CRYPTO_KDF_KEYBYTES !== \strlen($masterKey)) {
            throw new \InvalidArgumentException(\sprintf('APP_ENCRYPTION_KEY must be %d bytes, base64-encoded.', \SODIUM_CRYPTO_KDF_KEYBYTES));
        }

        // One sub-key per purpose, never the master key itself (see SodiumKeyedHasher).
        $this->key = sodium_crypto_kdf_derive_from_key(\SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 2, 'secretbx', $masterKey);
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext): EncryptedSecret
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return new EncryptedSecret(self::VERSION.base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key)));
    }

    public function decrypt(EncryptedSecret $secret): string
    {
        if (!str_starts_with($secret->value, self::VERSION)) {
            throw new \RuntimeException('Unknown encrypted secret format.');
        }

        $raw = base64_decode(substr($secret->value, \strlen(self::VERSION)), true);
        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Malformed encrypted secret.');
        }

        $plaintext = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if (false === $plaintext) {
            throw new \RuntimeException('Encrypted secret could not be authenticated (wrong key or tampered value).');
        }

        return $plaintext;
    }
}
