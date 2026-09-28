<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\User\User;

/**
 * Transactional emails of the Identity context. `$token` is the opaque value to put in the link.
 */
interface IdentityMailer
{
    public function sendEmailVerification(User $user, string $token): void;

    public function sendPasswordReset(User $user, string $token): void;
}
