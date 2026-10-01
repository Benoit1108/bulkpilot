<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\User;

final class SpyIdentityMailer implements IdentityMailer
{
    /** @var list<array{type: string, to: string, token: string}> */
    public array $sent = [];

    public function sendEmailVerification(User $user, string $token): void
    {
        $this->sent[] = ['type' => 'verification', 'to' => $user->email()->value, 'token' => $token];
    }

    public function sendPasswordReset(User $user, string $token): void
    {
        $this->sent[] = ['type' => 'reset', 'to' => $user->email()->value, 'token' => $token];
    }

    public function sendDeletionScheduled(User $user, \DateTimeImmutable $dueAt): void
    {
        $this->sent[] = ['type' => 'deletion_scheduled', 'to' => $user->email()->value, 'token' => $dueAt->format(\DATE_ATOM)];
    }

    public function sendDeletionCancelled(User $user, \DateTimeImmutable $at): void
    {
        $this->sent[] = ['type' => 'deletion_cancelled', 'to' => $user->email()->value, 'token' => ''];
    }

    public function sendAccountErased(EmailAddress $email, string $firstName): void
    {
        $this->sent[] = ['type' => 'account_erased', 'to' => $email->value, 'token' => ''];
    }

    public function lastToken(): string
    {
        $last = end($this->sent);
        if (false === $last) {
            throw new \LogicException('No email sent.');
        }

        return $last['token'];
    }
}
