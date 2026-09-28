<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;

final class EmailAndPasswordTest extends IdentityApiTestCase
{
    public function testTheEmailedLinkVerifiesTheAddressOnlyOnce(): void
    {
        $this->register();
        $token = $this->tokenFromLastEmail();

        $this->call('POST', '/api/auth/email-verification', ['token' => $token]);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/me');
        self::assertJsonContains(['emailVerified' => true]);

        $this->call('POST', '/api/auth/email-verification', ['token' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_token');
    }

    public function testTheLinkWorksFromAnotherDevice(): void
    {
        $this->register();
        $token = $this->tokenFromLastEmail();
        $this->useDevice('phone');

        $this->call('POST', '/api/auth/email-verification', ['token' => $token]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testAVerificationLinkCanBeResentUntilVerified(): void
    {
        $this->register();
        $first = $this->tokenFromLastEmail();

        $this->call('POST', '/api/me/email-verification');
        self::assertResponseStatusCodeSame(204);
        $second = $this->tokenFromLastEmail();
        self::assertNotSame($first, $second);

        $this->call('POST', '/api/auth/email-verification', ['token' => $first]);
        self::assertResponseStatusCodeSame(422);
        $this->call('POST', '/api/auth/email-verification', ['token' => $second]);
        self::assertResponseStatusCodeSame(204);

        $this->call('POST', '/api/me/email-verification');
        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('identity.email_already_verified');
    }

    public function testAPasswordResetSignsEveryDeviceOut(): void
    {
        $this->registerVerified();
        $this->useDevice('phone');

        $this->call('POST', '/api/auth/password-reset-request', ['email' => 'alex@example.com']);
        self::assertResponseStatusCodeSame(202);
        $token = $this->tokenFromLastEmail();
        self::assertStringContainsString('reinitialiser-mot-de-passe', (string) self::getMailerMessage()?->toString());

        $this->call('POST', '/api/auth/password-reset', ['token' => $token, 'newPassword' => 'a brand new passphrase']);
        self::assertResponseStatusCodeSame(204);

        $this->useDevice('laptop');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'the device signed in before the reset is signed out');

        $this->signIn(password: self::PASSWORD);
        self::assertResponseStatusCodeSame(401);
        $this->signIn(password: 'a brand new passphrase');
        self::assertResponseIsSuccessful();
    }

    public function testResetRequestsNeverRevealWhetherAnAccountExists(): void
    {
        $this->register('unverified@example.com');

        foreach (['nobody@example.com', 'unverified@example.com'] as $email) {
            $this->call('POST', '/api/auth/password-reset-request', ['email' => $email]);
            self::assertResponseStatusCodeSame(202);
            self::assertEmailCount(0);
        }
    }

    public function testAForgedResetLinkIsRejected(): void
    {
        $this->call('POST', '/api/auth/password-reset', ['token' => '0199a0b1-7c3e-7d4a-9b2f-1c2d3e4f5a6b.forged', 'newPassword' => 'a brand new passphrase']); // gitleaks:allow (fake token)

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.invalid_token');
    }

    public function testChangingThePasswordKeepsThisDeviceAndSignsOutTheOthers(): void
    {
        $this->register();
        $this->useDevice('phone');
        $this->signIn();
        self::assertResponseIsSuccessful();

        $this->call('POST', '/api/me/password', ['currentPassword' => 'not it at all', 'newPassword' => 'a brand new passphrase']);
        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.wrong_current_password');
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful('a wrong current password must not sign the device out');

        $this->call('POST', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a brand new passphrase']);
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->useDevice('laptop');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }
}
