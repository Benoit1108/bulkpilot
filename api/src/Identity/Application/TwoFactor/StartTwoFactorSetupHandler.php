<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

/**
 * Re-asking the password means a borrowed, unlocked device cannot enrol its own authenticator.
 */
final readonly class StartTwoFactorSetupHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordConfirmation $passwordConfirmation,
        private SecretCipher $cipher,
    ) {
    }

    public function __invoke(StartTwoFactorSetup $command): void
    {
        $user = $this->users->get($command->userId);
        $this->passwordConfirmation->assert($user, $command->password);

        $user->startTwoFactorEnrollment($this->cipher->encrypt($command->secret));
        $this->users->save($user);
    }
}
