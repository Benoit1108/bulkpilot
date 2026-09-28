<?php

declare(strict_types=1);

namespace App\Identity\Application\PasswordReset;

use App\Identity\Application\Port\IdentityMailer;
use App\Identity\Application\Token\OneTimeLinks;
use App\Identity\Domain\Exception\InvalidEmailAddress;
use App\Identity\Domain\Token\TokenPurpose;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Always succeeds from the caller's point of view: whether an account exists is never revealed.
 * Only a verified address receives a reset link (it is the proof of ownership).
 */
final readonly class RequestPasswordResetHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private OneTimeLinks $links,
        private IdentityMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        try {
            $user = $this->users->findByEmail(new EmailAddress($command->email));
        } catch (InvalidEmailAddress) {
            return;
        }
        if (null === $user || !$user->isEmailVerified()) {
            return;
        }

        $this->mailer->sendPasswordReset($user, $this->links->issue(TokenPurpose::PasswordReset, $user->id(), $this->clock->now()));
    }
}
