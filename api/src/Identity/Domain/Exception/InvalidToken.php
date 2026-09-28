<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidToken extends DomainException
{
    public static function create(): self
    {
        return new self('The link is invalid, expired or already used.');
    }

    public function errorCode(): string
    {
        return 'identity.invalid_token';
    }
}
