<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

final class TwoFactorAlreadyEnabled extends Conflict
{
    public static function create(): self
    {
        return new self('Two-factor authentication is already enabled.');
    }

    public function errorCode(): string
    {
        return 'identity.two_factor_already_enabled';
    }
}
