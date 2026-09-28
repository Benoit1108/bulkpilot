<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\KeyedHasher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * BLAKE2b keyed with a sub-key derived from APP_ENCRYPTION_KEY (never the encryption key itself).
 */
final readonly class SodiumKeyedHasher implements KeyedHasher
{
    private string $key;

    public function __construct(
        #[Autowire(env: 'base64:APP_ENCRYPTION_KEY')]
        #[\SensitiveParameter]
        string $masterKey,
    ) {
        if (\SODIUM_CRYPTO_KDF_KEYBYTES !== \strlen($masterKey)) {
            throw new \InvalidArgumentException(\sprintf('APP_ENCRYPTION_KEY must be %d bytes, base64-encoded.', \SODIUM_CRYPTO_KDF_KEYBYTES));
        }

        $this->key = sodium_crypto_kdf_derive_from_key(\SODIUM_CRYPTO_GENERICHASH_KEYBYTES, 1, 'recovery', $masterKey);
    }

    public function hash(string $value): string
    {
        return sodium_bin2hex(sodium_crypto_generichash($value, $this->key));
    }
}
