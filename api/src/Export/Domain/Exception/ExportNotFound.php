<?php

declare(strict_types=1);

namespace App\Export\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class ExportNotFound extends NotFound
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'export.not_found';
    }
}
