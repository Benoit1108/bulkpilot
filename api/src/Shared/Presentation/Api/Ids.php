<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Api;

use App\Shared\Domain\Exception\InvalidIdentifier;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\Identifier\Uuid;

/**
 * Identifiers from the URI: a malformed one is simply not found (as someone else's is).
 */
final class Ids
{
    /**
     * @template T of Uuid
     *
     * @param class-string<T>            $class
     * @param callable(string): NotFound $notFound
     *
     * @return T
     */
    public static function parse(mixed $id, string $class, callable $notFound): Uuid
    {
        $value = \is_string($id) ? $id : '';
        try {
            return new $class($value);
        } catch (InvalidIdentifier) {
            throw $notFound($value);
        }
    }
}
