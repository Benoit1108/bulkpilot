<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

/**
 * Previous codes stop working.
 */
final readonly class RegenerateRecoveryCodesHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordConfirmation $passwordConfirmation,
        private RecoveryCodes $recoveryCodes,
    ) {
    }

    public function __invoke(RegenerateRecoveryCodes $command): void
    {
        $user = $this->users->get($command->userId);
        $this->passwordConfirmation->assert($user, $command->password);

        $user->replaceRecoveryCodes($this->recoveryCodes->hashAll($command->recoveryCodes));
        $this->users->save($user);
    }
}
