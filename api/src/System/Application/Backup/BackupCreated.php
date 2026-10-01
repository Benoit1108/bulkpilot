<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

final readonly class BackupCreated
{
    public function __construct(public string $key, public int $deleted)
    {
    }
}
