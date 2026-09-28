<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Identifier;

use App\Shared\Domain\Identifier\UuidGenerator;
use Symfony\Component\Uid\UuidV7;

/**
 * UUID v7 are time-ordered: they keep B-tree indexes compact and sort naturally by creation time.
 */
final readonly class SymfonyUuidV7Generator implements UuidGenerator
{
    public function generate(): string
    {
        return new UuidV7()->toRfc4122();
    }
}
