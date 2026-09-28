<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\Port\KeyedHasher;
use App\Identity\Domain\User\User;

/**
 * Recovery codes are random (≈ 50 bits) and stored as keyed hashes: fast to check (one lookup,
 * not ten slow password verifications) and useless without the server key if the database leaks.
 * Online guessing is throttled by the second-factor rate limiter.
 */
final readonly class RecoveryCodes
{
    public function __construct(private KeyedHasher $hasher)
    {
    }

    /**
     * @param list<string> $plainCodes
     *
     * @return list<string>
     */
    public function hashAll(array $plainCodes): array
    {
        return array_map(fn (string $code): string => $this->hasher->hash(self::normalize($code)), $plainCodes);
    }

    /**
     * The stored hash matching the code, or null.
     */
    public function find(User $user, string $code): ?string
    {
        $hash = $this->hasher->hash(self::normalize($code));

        return \in_array($hash, $user->recoveryCodeHashes(), true) ? $hash : null;
    }

    public static function normalize(string $code): string
    {
        return strtolower(str_replace(['-', ' '], '', trim($code)));
    }
}
