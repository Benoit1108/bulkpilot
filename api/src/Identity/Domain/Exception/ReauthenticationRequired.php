<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * An account without a password proves it is its owner by a fresh sign-in (with its provider):
 * a 422, the PWA asks to sign in again, then retries.
 */
final class ReauthenticationRequired extends DomainException
{
    public static function create(int $minutes): self
    {
        return new self(\sprintf('Sign in again (within %d minutes) to confirm.', $minutes));
    }

    public function errorCode(): string
    {
        return 'identity.reauthentication_required';
    }
}
