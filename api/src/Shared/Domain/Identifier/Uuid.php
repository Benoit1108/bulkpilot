<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

use App\Shared\Domain\Exception\InvalidIdentifier;

/**
 * Base value object for every aggregate identifier (UserId, FoodId, WorkoutId...).
 *
 * The domain only validates and normalises the canonical RFC 9562 textual form: how an
 * identifier is generated (UUID v7) is an infrastructure concern, see {@see UuidGenerator}.
 */
abstract readonly class Uuid implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /** @var non-empty-string */
    public string $value;

    final public function __construct(string $value)
    {
        $normalized = strtolower($value);
        if ('' === $normalized || 1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidIdentifier::fromValue(static::class, $value);
        }

        $this->value = $normalized;
    }

    final public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
