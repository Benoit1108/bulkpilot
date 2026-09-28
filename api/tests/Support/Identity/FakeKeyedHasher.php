<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\KeyedHasher;

final class FakeKeyedHasher implements KeyedHasher
{
    public function hash(string $value): string
    {
        return 'keyed:'.$value;
    }
}
