<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface SecretGenerator
{
    /**
     * @return non-empty-string URL-safe, at least 256 bits of entropy
     */
    public function generate(): string;
}
