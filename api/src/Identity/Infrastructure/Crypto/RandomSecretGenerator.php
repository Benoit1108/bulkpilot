<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\SecretGenerator;

final readonly class RandomSecretGenerator implements SecretGenerator
{
    public function generate(): string
    {
        // 32 random bytes (256 bits), base64url without padding: 43 characters.
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        \assert('' !== $secret);

        return $secret;
    }
}
