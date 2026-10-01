<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

final readonly class VerifiedBackup
{
    public function __construct(public string $key, public RestoreCheck $check)
    {
    }
}
