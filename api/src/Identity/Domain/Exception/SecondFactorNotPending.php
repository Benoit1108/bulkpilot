<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationFailed;

final class SecondFactorNotPending extends AuthenticationFailed
{
    public static function create(): self
    {
        return new self('No sign-in is waiting for a second factor (or it expired).');
    }

    public function errorCode(): string
    {
        return 'identity.second_factor_not_pending';
    }
}
