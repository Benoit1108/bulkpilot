<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Domain\User\EncryptedSecret;

final class ReversibleCipher implements SecretCipher
{
    public function encrypt(string $plaintext): EncryptedSecret
    {
        return new EncryptedSecret('enc:'.$plaintext);
    }

    public function decrypt(EncryptedSecret $secret): string
    {
        return substr($secret->value, 4);
    }
}
