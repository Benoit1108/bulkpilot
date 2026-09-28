<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Application\Token\OneTimeLinks;
use App\Identity\Domain\Token\TokenPurpose;
use App\Identity\Domain\User\User;

/**
 * Issues a fresh verification link (previous ones stop working) and emails it.
 */
final readonly class EmailVerificationSender
{
    public function __construct(private OneTimeLinks $links, private IdentityMailer $mailer)
    {
    }

    public function send(User $user, \DateTimeImmutable $now): void
    {
        $this->mailer->sendEmailVerification($user, $this->links->issue(TokenPurpose::EmailVerification, $user->id(), $now));
    }
}
