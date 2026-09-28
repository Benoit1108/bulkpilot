<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\User\HashedPassword;

/**
 * Reversible stand-in: "hashed:<plain>". Real hashing is covered by integration tests.
 */
final class FakePasswordHasher implements PasswordHasher
{
    public int $dummyVerifications = 0;

    public function hash(string $plainPassword): HashedPassword
    {
        return new HashedPassword('hashed:'.$plainPassword);
    }

    public function verify(?HashedPassword $hashed, string $plainPassword): bool
    {
        if (null === $hashed) {
            $this->verifyAgainstDummy($plainPassword);

            return false;
        }

        return 'hashed:'.$plainPassword === $hashed->value;
    }

    public function verifyAgainstDummy(string $plainPassword): void
    {
        ++$this->dummyVerifications;
    }
}
