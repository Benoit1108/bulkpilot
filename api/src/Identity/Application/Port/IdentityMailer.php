<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\User;

/**
 * Transactional emails of the Identity context. `$token` is the opaque value to put in the link.
 */
interface IdentityMailer
{
    public function sendEmailVerification(User $user, string $token): void;

    public function sendPasswordReset(User $user, string $token): void;

    /** The account is closed and will be erased on `$dueAt` unless the user signs in again. */
    public function sendDeletionScheduled(User $user, \DateTimeImmutable $dueAt): void;

    public function sendDeletionCancelled(User $user, \DateTimeImmutable $at): void;

    /** Sent once the account is gone: only the address and first name remain, captured before. */
    public function sendAccountErased(EmailAddress $email, string $firstName): void;
}
