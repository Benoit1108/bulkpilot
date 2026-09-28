<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Port\KeyedHasher;
use App\Identity\Application\Port\RecoveryCodeGenerator;
use App\Identity\Application\Port\SecretCipher;
use App\Identity\Application\Port\TotpAuthenticator;
use App\Identity\Domain\User\EncryptedSecret;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TwoFactorAdaptersTest extends KernelTestCase
{
    public function testSecretsAreEncryptedWithAFreshNonceAndAuthenticated(): void
    {
        $cipher = self::getContainer()->get(SecretCipher::class);

        $first = $cipher->encrypt('JBSWY3DPEHPK3PXP');
        $second = $cipher->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('v1:', $first->value);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $first->value);
        self::assertNotSame($first->value, $second->value, 'random nonce');
        self::assertSame('JBSWY3DPEHPK3PXP', $cipher->decrypt($first));

        $raw = (string) base64_decode(substr($first->value, 3), true);
        $raw[-1] = \chr(\ord($raw[-1]) ^ 1);
        $this->expectExceptionMessage('could not be authenticated');
        $cipher->decrypt(new EncryptedSecret('v1:'.base64_encode($raw)));
    }

    public function testCodesFromARealAuthenticatorAreAcceptedWithinOneStepOfDrift(): void
    {
        $totp = self::getContainer()->get(TotpAuthenticator::class);
        $secret = $totp->generateSecret();
        $now = new \DateTimeImmutable('2026-09-28 10:00:15');
        $app = TOTP::createFromSecret($secret);
        $t = max(60, $now->getTimestamp());

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret, '160-bit Base32 seed');
        self::assertSame(intdiv($now->getTimestamp(), 30), $totp->matchingStep($secret, $app->at($t), $now));
        self::assertNotNull($totp->matchingStep($secret, $app->at($t - 30), $now), 'previous step');
        self::assertNotNull($totp->matchingStep($secret, $app->at($t + 30), $now), 'next step');
        self::assertNull($totp->matchingStep($secret, $app->at($t - 60), $now), 'two steps old');
        self::assertStringStartsWith('otpauth://totp/BulkPilot%3Aalex%40example.com?', $totp->provisioningUri($secret, 'alex@example.com'));
        self::assertStringContainsString('issuer=BulkPilot', $totp->provisioningUri($secret, 'alex@example.com'));
    }

    public function testRecoveryCodeHashesAreKeyedAndDeterministic(): void
    {
        $hasher = self::getContainer()->get(KeyedHasher::class);

        self::assertSame($hasher->hash('k7m3px9w2c'), $hasher->hash('k7m3px9w2c'));
        self::assertNotSame($hasher->hash('k7m3px9w2c'), $hasher->hash('k7m3px9w2d'));
        self::assertNotSame(hash('sha256', 'k7m3px9w2c'), $hasher->hash('k7m3px9w2c'));
        self::assertNotSame(bin2hex(sodium_crypto_generichash('k7m3px9w2c')), $hasher->hash('k7m3px9w2c'), 'keyed, not a plain BLAKE2b');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hasher->hash('k7m3px9w2c'));
    }

    public function testRecoveryCodesAreReadableAndUnique(): void
    {
        $codes = self::getContainer()->get(RecoveryCodeGenerator::class)->generate(10);

        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/', $code);
        }
    }
}
