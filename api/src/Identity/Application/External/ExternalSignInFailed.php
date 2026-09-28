<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Shared\Domain\Exception\AuthenticationFailed;

final class ExternalSignInFailed extends AuthenticationFailed
{
    public static function because(string $reason): self
    {
        return new self('External sign-in failed: '.$reason);
    }

    public function errorCode(): string
    {
        return 'identity.external_sign_in_failed';
    }
}
