<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\Port\SecretCipher;
use App\Identity\Application\Port\TotpAuthenticator;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\User\User;

/**
 * Checks a second factor: an app code is accepted once (replay protection), a recovery code is
 * burnt when used. The caller saves the user and publishes its events.
 */
final readonly class SecondFactorCheck
{
    public function __construct(private SecretCipher $cipher, private TotpAuthenticator $totp, private RecoveryCodes $recoveryCodes)
    {
    }

    /**
     * @throws InvalidSecondFactorCode
     */
    public function verify(User $user, string $code, \DateTimeImmutable $now): void
    {
        $secret = $user->totpSecret() ?? throw InvalidSecondFactorCode::create();
        $digits = str_replace([' ', '-'], '', trim($code));
        if (1 === preg_match('/^\d{6}$/', $digits)) {
            $step = $this->totp->matchingStep($this->cipher->decrypt($secret), $digits, $now) ?? throw InvalidSecondFactorCode::create();
            $user->acceptTotpStep($step);

            return;
        }
        $user->consumeRecoveryCode($this->recoveryCodes->find($user, $code) ?? throw InvalidSecondFactorCode::create(), $now);
    }
}
