<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidEmailAddress extends DomainException
{
    public static function fromValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid email address.', mb_substr($value, 0, 80)));
    }

    public function errorCode(): string
    {
        return 'identity.invalid_email';
    }
}
