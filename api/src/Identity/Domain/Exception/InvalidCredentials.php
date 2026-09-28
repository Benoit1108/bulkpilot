<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationFailed;

final class InvalidCredentials extends AuthenticationFailed
{
    public static function create(): self
    {
        return new self('Unknown email or wrong password.');
    }

    public function errorCode(): string
    {
        return 'identity.invalid_credentials';
    }
}
