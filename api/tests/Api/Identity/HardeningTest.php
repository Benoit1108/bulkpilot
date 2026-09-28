<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;
use OTPHP\TOTP;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Security properties found in review: time limits, replay, throttling, account edge cases.
 */
final class HardeningTest extends IdentityApiTestCase
{
    use ClockSensitiveTrait;

    public function testAPendingSecondFactorExpiresAfterFiveMinutes(): void
    {
        $this->client->disableReboot();
        $secret = $this->enableTwoFactor();
        $this->call('POST', '/api/auth/sign-out');

        self::mockTime('+1 minute');
        $this->signIn();
        self::mockTime('+301 seconds');
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => $this->codeAt($secret, time() + 301)]);

        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('identity.second_factor_not_pending');
    }

    public function testAnAppCodeIsNeverAcceptedTwice(): void
    {
        $secret = $this->enableTwoFactor();
        $code = $this->codeAt($secret, time() + 30);
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => $code]);
        self::assertResponseIsSuccessful();
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => $code]);
        self::assertResponseStatusCodeSame(422, 'the same step is refused, even from a new sign-in');
    }

    public function testAppCodesMayBeTypedWithASpace(): void
    {
        $secret = $this->enableTwoFactor();
        $code = $this->codeAt($secret, time() + 30);
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn();
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => substr($code, 0, 3).' '.substr($code, 3)]);

        self::assertResponseIsSuccessful();
    }

    public function testTwoFactorStillAppliesAfterAPasswordReset(): void
    {
        $this->enableTwoFactor();
        $this->call('POST', '/api/auth/sign-out');
        $this->call('POST', '/api/auth/password-reset-request', ['email' => 'alex@example.com']);
        $this->call('POST', '/api/auth/password-reset', ['token' => $this->tokenFromLastEmail(), 'newPassword' => 'a brand new passphrase']);

        $this->signIn(password: 'a brand new passphrase');

        self::assertJsonContains(['twoFactorRequired' => true]);
    }

    public function testSecondFactorGuessesAreCountedPerAccountAcrossSessions(): void
    {
        $this->client->disableReboot();
        $this->enableTwoFactor();
        $this->call('POST', '/api/auth/sign-out');

        foreach (['a', 'b'] as $device) {
            $this->useDevice($device);
            $this->signIn();
            for ($i = 0; $i < 3 && !('b' === $device && 2 === $i); ++$i) {
                $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => '00000'.$i]);
                self::assertResponseStatusCodeSame(422);
            }
        }
        $this->call('POST', '/api/auth/sign-in/second-factor', ['code' => '999999']);

        self::assertResponseStatusCodeSame(429, 'five guesses spread over two devices exhaust the account budget');
    }

    public function testPasswordConfirmationsAreThrottledFromASignedInSession(): void
    {
        $this->client->disableReboot();
        $this->registerVerified();

        for ($i = 0; $i < 10; ++$i) {
            $this->call('POST', '/api/me/two-factor/disable', ['password' => 'guess '.$i]);
        }
        $this->call('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a brand new passphrase']);

        self::assertResponseStatusCodeSame(429, 'a stolen session cannot be used to guess the password');
    }

    public function testRegistrationIsThrottledPerClient(): void
    {
        $this->client->disableReboot();
        for ($i = 0; $i < 5; ++$i) {
            $this->useDevice('d'.$i);
            $this->register(\sprintf('user%d@example.com', $i));
        }

        $this->useDevice('d5');
        $this->call('POST', '/api/auth/register', ['firstName' => 'X', 'email' => 'user5@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(429);
    }

    public function testResetRequestsAreThrottledPerEmail(): void
    {
        $this->client->disableReboot();
        $this->registerVerified();

        // The verification above used one attempt of this client's budget; each request also counts for the email.
        for ($i = 0; $i < 9; ++$i) {
            $this->call('POST', '/api/auth/password-reset-request', ['email' => 0 === $i % 2 ? 'ALEX@example.com' : 'alex@example.com']);
            self::assertResponseStatusCodeSame(202);
        }
        $this->call('POST', '/api/auth/password-reset-request', ['email' => 'alex@example.com']);

        self::assertResponseStatusCodeSame(429);
    }

    public function testAVerificationLinkCannotResetAPassword(): void
    {
        $this->register();

        $this->call('POST', '/api/auth/password-reset', ['token' => $this->tokenFromLastEmail(), 'newPassword' => 'a brand new passphrase']);

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_token');
    }

    public function testVerificationLinksExpireAfterSevenDays(): void
    {
        $this->client->disableReboot();
        $this->register();
        $token = $this->tokenFromLastEmail();

        self::mockTime('+7 days');
        $this->call('POST', '/api/auth/email-verification', ['token' => $token]);

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_token');
    }

    public function testADeviceIdleForThirtyDaysIsSignedOut(): void
    {
        $this->client->disableReboot();
        $this->register();

        self::mockTime('+30 days');
        $this->call('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSigningInAgainOnTheSameDeviceReplacesItsSession(): void
    {
        $this->register();
        $this->signIn();

        self::assertCount(1, $this->listSessions(), 'no orphan entry for the previous session of this device');
    }

    public function testImpossibleBirthDatesAreRejectedNotRolledOver(): void
    {
        $this->register();

        $this->call('PUT', '/api/me/profile', ['firstName' => 'Alex', 'birthDate' => '2000-13-45']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'birthDate']]]);
    }

    public function testAFirstNameIsMeasuredAfterTrimming(): void
    {
        $this->register();

        $this->call('PUT', '/api/me/profile', ['firstName' => '  '.str_repeat('a', 50).'  ']);

        self::assertResponseIsSuccessful();
    }

    public function testTheSessionCookieIsRenewedOnceADay(): void
    {
        $this->client->disableReboot();
        $this->register();
        self::assertNull($this->sessionCookieSet($this->call('GET', '/api/me')), 'not on every request');

        self::mockTime('+25 hours');

        self::assertNotNull($this->sessionCookieSet($this->call('GET', '/api/me')), 'a daily user is never signed out by the cookie expiry');
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful('the renewed session keeps the user signed in');
    }

    private function sessionCookieSet(ResponseInterface $response): ?string
    {
        foreach ($response->getHeaders(false)['set-cookie'] ?? [] as $cookie) {
            if (str_starts_with($cookie, 'MOCKSESSID=') || str_starts_with($cookie, 'sid=')) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return non-empty-string
     */
    private function enableTwoFactor(): string
    {
        $this->registerVerified();
        $secret = $this->call('POST', '/api/me/two-factor/setup', ['password' => self::PASSWORD])->toArray()['secret'];
        \assert(\is_string($secret) && '' !== $secret);
        $this->call('POST', '/api/me/two-factor/confirm', ['code' => TOTP::createFromSecret($secret)->now()]);
        self::assertResponseIsSuccessful();

        return $secret;
    }

    /**
     * @param non-empty-string $secret
     */
    private function codeAt(string $secret, int $timestamp): string
    {
        return TOTP::createFromSecret($secret)->at(max(0, $timestamp));
    }
}
