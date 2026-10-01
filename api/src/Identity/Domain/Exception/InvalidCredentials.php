<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\AuthenticationFailed;
use App\Shared\Domain\Identifier\UserId;

final class InvalidCredentials extends AuthenticationFailed
{
    /** The account whose password was wrong, for its owner's activity; never told to the client. */
    public ?UserId $account = null;

    public static function create(): self
    {
        return new self('Unknown email or wrong password.');
    }

    public static function forAccount(UserId $account): self
    {
        $exception = self::create();
        $exception->account = $account;

        return $exception;
    }

    public function errorCode(): string
    {
        return 'identity.invalid_credentials';
    }
}
