<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

/**
 * Output of the password hasher (never the plain password).
 */
final readonly class HashedPassword
{
    /**
     * @param non-empty-string $value
     */
    public function __construct(public string $value)
    {
    }
}
