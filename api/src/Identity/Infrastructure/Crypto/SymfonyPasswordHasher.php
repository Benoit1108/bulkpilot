<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\User\HashedPassword;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Uses the algorithm configured for SecurityUser in security.yaml ("auto": Argon2id or bcrypt).
 */
final class SymfonyPasswordHasher implements PasswordHasher
{
    /** Hash of a random value, computed once per process: not user data, safe in a long-lived worker. */
    private ?string $dummyHash = null;

    public function __construct(private readonly PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(string $plainPassword): HashedPassword
    {
        $hash = $this->hasher()->hash($plainPassword);
        \assert('' !== $hash);

        return new HashedPassword($hash);
    }

    public function verify(?HashedPassword $hashed, string $plainPassword): bool
    {
        if (null === $hashed) {
            $this->verifyAgainstDummy($plainPassword);

            return false;
        }

        return $this->hasher()->verify($hashed->value, $plainPassword);
    }

    public function verifyAgainstDummy(string $plainPassword): void
    {
        $this->dummyHash ??= $this->hasher()->hash(bin2hex(random_bytes(16)));
        $this->hasher()->verify($this->dummyHash, $plainPassword);
    }

    private function hasher(): PasswordHasherInterface
    {
        return $this->factory->getPasswordHasher(SecurityUser::class);
    }
}
