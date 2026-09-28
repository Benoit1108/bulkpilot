<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\User\PasswordPolicy;
use App\Shared\Domain\Exception\DomainException;

final class WeakPassword extends DomainException
{
    public static function create(): self
    {
        return new self(\sprintf('Passwords must contain %d to %d characters.', PasswordPolicy::MIN_LENGTH, PasswordPolicy::MAX_LENGTH));
    }

    public function errorCode(): string
    {
        return 'identity.weak_password';
    }
}
