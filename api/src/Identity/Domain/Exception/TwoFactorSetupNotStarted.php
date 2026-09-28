<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class TwoFactorSetupNotStarted extends DomainException
{
    public static function create(): self
    {
        return new self('No two-factor setup in progress.');
    }

    public function errorCode(): string
    {
        return 'identity.two_factor_setup_not_started';
    }
}
