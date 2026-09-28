<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidProfile extends DomainException
{
    public static function because(string $field, string $reason): self
    {
        return new self(\sprintf('Invalid %s: %s', $field, $reason));
    }

    public function errorCode(): string
    {
        return 'identity.invalid_profile';
    }
}
