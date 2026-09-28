<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class TwoFactorNotEnabled extends DomainException
{
    public static function create(): self
    {
        return new self('Two-factor authentication is not enabled.');
    }

    public function errorCode(): string
    {
        return 'identity.two_factor_not_enabled';
    }
}
