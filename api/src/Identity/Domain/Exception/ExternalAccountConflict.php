<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\Conflict;

/**
 * An account already uses this email but it is not verified (or the provider's is not): linking
 * automatically would let whoever created that account keep access to the victim's (pre-hijacking).
 */
final class ExternalAccountConflict extends Conflict
{
    public static function create(): self
    {
        return new self('An account with this email exists; sign in with its password first.');
    }

    public function errorCode(): string
    {
        return 'identity.external_account_conflict';
    }
}
