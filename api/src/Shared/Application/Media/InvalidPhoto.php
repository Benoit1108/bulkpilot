<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Domain\Exception\DomainException;

final class InvalidPhoto extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'media.invalid_photo';
    }
}
