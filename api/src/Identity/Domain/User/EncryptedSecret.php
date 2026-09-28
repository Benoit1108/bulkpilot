<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

/**
 * A secret encrypted at rest (e.g. the TOTP seed). Opaque to the domain: only the SecretCipher
 * port can read it, so a database leak alone does not yield usable second factors.
 */
final readonly class EncryptedSecret
{
    /**
     * @param non-empty-string $value
     */
    public function __construct(public string $value)
    {
    }
}
