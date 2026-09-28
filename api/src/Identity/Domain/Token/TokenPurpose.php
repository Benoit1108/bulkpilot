<?php

declare(strict_types=1);

namespace App\Identity\Domain\Token;

enum TokenPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';

    public function lifetimeSeconds(): int
    {
        return match ($this) {
            self::EmailVerification => 7 * 24 * 3600,
            self::PasswordReset => 3600,
        };
    }
}
