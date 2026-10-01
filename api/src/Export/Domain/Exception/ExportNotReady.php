<?php

declare(strict_types=1);

namespace App\Export\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class ExportNotReady extends Conflict
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'export.not_ready';
    }
}
