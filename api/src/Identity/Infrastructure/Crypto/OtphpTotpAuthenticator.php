<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Crypto;

use App\Identity\Application\Port\TotpAuthenticator;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * SHA-1 / 6 digits / 30 s: the parameters every authenticator app supports.
 */
final readonly class OtphpTotpAuthenticator implements TotpAuthenticator
{
    private const int PERIOD = 30;
    private const int DRIFT_STEPS = 1;

    public function __construct(
        #[Autowire(env: 'APP_DISPLAY_NAME')]
        private string $issuer,
    ) {
    }

    public function generateSecret(): string
    {
        // 160-bit seed, as recommended by RFC 4226.
        $secret = rtrim(Base32::encodeUpper(random_bytes(20)), '=');
        \assert('' !== $secret);

        return $secret;
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        \assert('' !== $accountName && '' !== $this->issuer);
        $totp = $this->totp($secret);
        $totp->setLabel($accountName);
        $totp->setIssuer($this->issuer);

        return $totp->getProvisioningUri();
    }

    public function matchingStep(string $secret, string $code, \DateTimeImmutable $now): ?int
    {
        $totp = $this->totp($secret);
        $current = intdiv($now->getTimestamp(), self::PERIOD);

        for ($step = $current - self::DRIFT_STEPS; $step <= $current + self::DRIFT_STEPS; ++$step) {
            $timestamp = max(0, $step * self::PERIOD);
            if (hash_equals($totp->at($timestamp), $code)) {
                return $step;
            }
        }

        return null;
    }

    private function totp(string $secret): TOTP
    {
        if ('' === $secret) {
            throw new \InvalidArgumentException('Empty TOTP secret.');
        }

        return TOTP::createFromSecret($secret);
    }
}
