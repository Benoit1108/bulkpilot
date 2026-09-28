<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

interface UuidGenerator
{
    /**
     * Returns a new time-ordered identifier in canonical lowercase form.
     *
     * @return non-empty-string
     */
    public function generate(): string;
}
