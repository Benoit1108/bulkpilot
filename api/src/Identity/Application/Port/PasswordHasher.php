<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\User\HashedPassword;

interface PasswordHasher
{
    public function hash(string $plainPassword): HashedPassword;

    /**
     * False for accounts without a password (created through an external provider), in the same time.
     */
    public function verify(?HashedPassword $hashed, string $plainPassword): bool;

    /**
     * Burns the same time as a real verification when no user matches (no account enumeration by timing).
     */
    public function verifyAgainstDummy(string $plainPassword): void;
}
