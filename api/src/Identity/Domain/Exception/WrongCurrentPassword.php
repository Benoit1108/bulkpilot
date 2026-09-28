<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Not an authentication failure (the caller is signed in): a 422, so the PWA keeps the session.
 */
final class WrongCurrentPassword extends DomainException
{
    public static function create(): self
    {
        return new self('The current password is wrong.');
    }

    public function errorCode(): string
    {
        return 'identity.wrong_current_password';
    }
}
