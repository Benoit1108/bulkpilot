<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

use App\Shared\Domain\Exception\AuthenticationFailed;

final class NotAuthenticated extends AuthenticationFailed
{
    public function errorCode(): string
    {
        return 'shared.not_authenticated';
    }
}
