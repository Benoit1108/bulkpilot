<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Exception\WeakPassword;

/**
 * Length is what matters (NIST SP 800-63B): no composition rules, long passphrases welcome.
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 12;
    public const int MAX_LENGTH = 4096;

    public static function assertAcceptable(string $plainPassword): void
    {
        $length = mb_strlen($plainPassword);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw WeakPassword::create();
        }
    }
}
