<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

/**
 * RFC 6238 time-based one-time passwords (6 digits, 30 s, the format authenticator apps use).
 */
interface TotpAuthenticator
{
    /**
     * @return non-empty-string new Base32 seed
     */
    public function generateSecret(): string;

    /**
     * otpauth:// URI encoded in the QR code scanned by the authenticator app.
     */
    public function provisioningUri(string $secret, string $accountName): string;

    /**
     * Time step of the code if it is valid now (±1 step for clock drift), null otherwise.
     */
    public function matchingStep(string $secret, string $code, \DateTimeImmutable $now): ?int;
}
