<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface RecoveryCodeGenerator
{
    /**
     * @return list<non-empty-string> easy to read and type (no ambiguous characters)
     */
    public function generate(int $count): array;
}
