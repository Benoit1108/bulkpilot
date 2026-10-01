<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;
use OTPHP\TOTP;

final class TwoFactorTest extends IdentityApiTestCase
{
    public function testEnablingThenSigningInWithAnAuthenticatorCode(): void
    {
        $secret = $this->enableTwoFactor();
        $this->call('GET', '/api/me');
        self::assertJsonContains(['twoFactorEnabled' => true]);
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        self::assertResponseIsSuccessful();
        self::assertJsonEquals(['twoFactorRequired' => true, 'account' => null, 'deletionCancelled' => false]);
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'the password alone does not sign in');

        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => '000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_second_factor_code');

        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => $this->nextCode($secret)]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['twoFactorRequired' => false, 'account' => ['email' => 'alex@example.com']]);
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testARecoveryCodeSignsInOnce(): void
    {
        $this->enableTwoFactor();
        $codes = $this->recoveryCodes;
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => strtoupper($codes[0])]);
        self::assertResponseIsSuccessful();
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => $codes[0]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheSecondStepNeedsAPendingSignIn(): void
    {
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => '123456']);

        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('identity.second_factor_not_pending');
    }

    public function testCodeGuessingIsThrottledPerAccount(): void
    {
        $this->client->disableReboot();
        $this->enableTwoFactor();
        $this->call('POST', '/api/auth/sign-out');
        $this->signIn();

        for ($i = 0; $i < 5; ++$i) {
            $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => \sprintf('%06d', $i)]);
            self::assertResponseStatusCodeSame(422);
        }
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => '999999']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testEnrolmentRequiresAVerifiedEmailAndThePassword(): void
    {
        $this->register();
        $this->call('POST', '/api/me/two-factor/setup', ['password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.email_not_verified');

        $this->useDevice('verified');
        $this->registerVerified('sam@example.com');
        $this->call('POST', '/api/me/two-factor/setup', ['password' => 'not the password']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.wrong_current_password');
    }

    public function testRecoveryCodesCanBeRegeneratedAndTwoFactorDisabled(): void
    {
        $this->enableTwoFactor();
        $old = $this->recoveryCodes;

        $fresh = $this->call('POST', '/api/me/two-factor/recovery-codes', ['password' => self::PASSWORD])->toArray()['recoveryCodes'];
        self::assertIsArray($fresh);
        self::assertCount(10, $fresh);
        self::assertNotSame($old, $fresh);

        $this->call('POST', '/api/me/two-factor/disable', ['password' => 'wrong']);
        self::assertResponseStatusCodeSame(422);
        $this->call('POST', '/api/me/two-factor/disable', ['password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(204);

        $this->call('POST', '/api/auth/sign-out');
        $this->signIn();
        self::assertJsonContains(['twoFactorRequired' => false]);
    }

    /** @var list<string> */
    private array $recoveryCodes = [];

    private function enableTwoFactor(): string
    {
        $this->registerVerified();
        $setup = $this->call('POST', '/api/me/two-factor/setup', ['password' => self::PASSWORD])->toArray();
        self::assertResponseIsSuccessful();
        self::assertIsString($setup['secret']);
        self::assertIsString($setup['otpauthUri']);
        self::assertStringStartsWith('otpauth://totp/', $setup['otpauthUri']);

        $this->call('POST', '/api/me/two-factor/confirm', ['code' => 'abc']);
        self::assertResponseStatusCodeSame(422, 'only 6 digits are accepted');

        self::assertNotSame('', $setup['secret']);
        $confirmation = $this->call('POST', '/api/me/two-factor/confirm', ['code' => TOTP::createFromSecret($setup['secret'])->now()])->toArray();
        self::assertResponseIsSuccessful();
        $codes = $confirmation['recoveryCodes'];
        self::assertIsArray($codes);
        self::assertCount(10, $codes);
        $this->recoveryCodes = array_values(array_filter($codes, is_string(...)));

        return $setup['secret'];
    }

    /**
     * The enrolment code's step is already used: the next valid code is one step ahead.
     */
    private function nextCode(string $secret): string
    {
        \assert('' !== $secret);

        return TOTP::createFromSecret($secret)->at(time() + 30);
    }
}
