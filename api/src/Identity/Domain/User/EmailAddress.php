<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Exception\InvalidEmailAddress;

/**
 * Login identifier. Stored trimmed and lowercased so that uniqueness is case-insensitive.
 */
final readonly class EmailAddress implements \Stringable
{
    /** @var non-empty-string */
    public string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));
        if ('' === $normalized || mb_strlen($normalized) > 254 || false === filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmailAddress::fromValue($value);
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
