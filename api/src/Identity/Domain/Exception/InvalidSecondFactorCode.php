<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidSecondFactorCode extends DomainException
{
    public static function create(): self
    {
        return new self('The code is invalid, expired or already used.');
    }

    public function errorCode(): string
    {
        return 'identity.invalid_second_factor_code';
    }
}
