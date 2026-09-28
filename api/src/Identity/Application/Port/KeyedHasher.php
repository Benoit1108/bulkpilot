<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

/**
 * Deterministic hash keyed with a server-side secret: equal inputs give equal hashes (direct
 * lookup), but a database leak alone does not allow testing guesses offline.
 */
interface KeyedHasher
{
    /**
     * @return non-empty-string
     */
    public function hash(string $value): string;
}
