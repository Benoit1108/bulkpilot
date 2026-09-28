<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Application\Port\TotpAuthenticator;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * An app code is accepted once (replay protection); a recovery code is burnt when used.
 */
final readonly class VerifySecondFactorHandler implements CommandHandler
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

    public function __invoke(VerifySecondFactor $command): void
    {
        $user = $this->users->get($command->userId);
        $secret = $user->totpSecret() ?? throw InvalidSecondFactorCode::create();
        $now = $this->clock->now();
        $code = str_replace([' ', '-'], '', trim($command->code));

        if (1 === preg_match('/^\d{6}$/', $code)) {
            $step = $this->totp->matchingStep($this->cipher->decrypt($secret), $code, $now) ?? throw InvalidSecondFactorCode::create();
            $user->acceptTotpStep($step);
        } else {
            $user->consumeRecoveryCode($this->recoveryCodes->find($user, $command->code) ?? throw InvalidSecondFactorCode::create(), $now);
        }

        $this->users->save($user);
        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
