<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Previous codes stop working.
 */
final readonly class RegenerateRecoveryCodesHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordConfirmation $passwordConfirmation,
        private RecoveryCodes $recoveryCodes,
        private AuditTrail $audit,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegenerateRecoveryCodes $command): void
    {
        $user = $this->users->get($command->userId);
        $this->passwordConfirmation->assert($user, $command->password);

        $user->replaceRecoveryCodes($this->recoveryCodes->hashAll($command->recoveryCodes));
        $this->users->save($user);
        $this->audit->record($user->id(), 'identity.recovery_codes_regenerated', [], 'user', $this->clock->now());
    }
}
