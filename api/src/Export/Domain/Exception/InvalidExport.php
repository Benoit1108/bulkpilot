<?php

declare(strict_types=1);

namespace App\Export\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidExport extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'export.invalid_scope';
    }
}
