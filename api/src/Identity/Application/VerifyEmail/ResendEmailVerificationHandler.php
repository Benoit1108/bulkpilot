<?php

declare(strict_types=1);

namespace App\Identity\Application\VerifyEmail;

use App\Identity\Domain\Exception\EmailAlreadyVerified;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ResendEmailVerificationHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationSender $emailVerification,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ResendEmailVerification $command): void
    {
        $user = $this->users->get($command->userId);
        if ($user->isEmailVerified()) {
            throw EmailAlreadyVerified::forUser($user->id());
        }

        $this->emailVerification->send($user, $this->clock->now());
    }
}
