<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Application\Port\TotpAuthenticator;

/**
 * Codes are "<secret-derived>" per 30 s step: code(step) = sprintf('%06d', step % 1000000).
 */
final class FakeTotpAuthenticator implements TotpAuthenticator
{
    public function generateSecret(): string
    {
        return 'JBSWY3DPEHPK3PXP';
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        return 'otpauth://totp/'.$accountName.'?secret='.$secret;
    }

    public function matchingStep(string $secret, string $code, \DateTimeImmutable $now): ?int
    {
        $current = intdiv($now->getTimestamp(), 30);
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if (self::codeAt($step) === $code) {
                return $step;
            }
        }

        return null;
    }

    public static function codeAt(int $step): string
    {
        return \sprintf('%06d', $step % 1000000);
    }

    public static function codeFor(\DateTimeImmutable $now): string
    {
        return self::codeAt(intdiv($now->getTimestamp(), 30));
    }
}
