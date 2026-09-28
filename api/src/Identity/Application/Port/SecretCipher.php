<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\User\EncryptedSecret;

interface SecretCipher
{
    public function encrypt(string $plaintext): EncryptedSecret;

    public function decrypt(EncryptedSecret $secret): string;
}
