<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Application\Port\TotpAuthenticator;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\TwoFactorSetupNotStarted;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * A valid first code proves the app is set up correctly; only then is the second factor required.
 */
final readonly class ConfirmTwoFactorHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private SecretCipher $cipher,
        private TotpAuthenticator $totp,
        private RecoveryCodes $recoveryCodes,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ConfirmTwoFactor $command): void
    {
        $user = $this->users->get($command->userId);
        $pending = $user->pendingTotpSecret() ?? throw TwoFactorSetupNotStarted::create();

        $now = $this->clock->now();
        $step = $this->totp->matchingStep($this->cipher->decrypt($pending), str_replace(' ', '', trim($command->code)), $now) ?? throw InvalidSecondFactorCode::create();

        $user->confirmTwoFactor($step, $this->recoveryCodes->hashAll($command->recoveryCodes), $now);
        $this->users->save($user);

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
