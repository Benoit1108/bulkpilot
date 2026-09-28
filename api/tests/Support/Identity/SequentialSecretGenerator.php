<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\SecretGenerator;

final class SequentialSecretGenerator implements SecretGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return 'secret-'.$this->next++;
    }
}
