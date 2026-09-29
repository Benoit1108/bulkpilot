<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

/**
 * A stored file (a progress photo), shared so that a context can refer to what Media keeps
 * without depending on it (ADR-0027).
 */
final readonly class MediaId extends Uuid
{
}
