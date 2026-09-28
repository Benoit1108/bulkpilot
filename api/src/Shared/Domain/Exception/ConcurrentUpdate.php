<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Another request changed the same data at the same moment (double submit, parallel devices):
 * the second one is refused instead of silently overwriting or duplicating.
 */
final class ConcurrentUpdate extends Conflict
{
    public static function create(): self
    {
        return new self('The data was changed by another request at the same time; retry.');
    }

    public function errorCode(): string
    {
        return 'shared.concurrent_update';
    }
}
