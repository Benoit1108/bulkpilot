<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Identity\Application\Port\RegistrationPolicy;
use App\Tests\Support\Api\IdentityApiTestCase;
use Symfony\Component\Mime\Email;

final class RegistrationAndSignInTest extends IdentityApiTestCase
{
    public function testRegistrationSignsTheDeviceInAndEmailsAVerificationLink(): void
    {
        $response = $this->call('POST', '/api/auth/register', ['firstName' => 'Alex', 'email' => 'Alex@Example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['email' => 'alex@example.com', 'emailVerified' => false, 'firstName' => 'Alex']);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('alex@example.com', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('https://localhost/verifier-email?token=', (string) $email->getTextBody());
        self::assertArrayNotHasKey('password', $response->toArray());

        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => 'alex@example.com']);
    }

    public function testAnEmailCanOnlyBeRegisteredOnce(): void
    {
        $this->register();

        $this->call('POST', '/api/auth/register', ['firstName' => 'Sam', 'email' => 'ALEX@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(409);
        self::assertProblemCode('identity.email_already_used');
    }

    public function testInvalidRegistrationsAreRejectedFieldByField(): void
    {
        $this->call('POST', '/api/auth/register', ['firstName' => ' ', 'email' => 'nope', 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'firstName'], ['propertyPath' => 'email'], ['propertyPath' => 'password']]]);
        self::assertEmailCount(0);
    }

    public function testRegistrationCanBeClosed(): void
    {
        $this->client->disableReboot();
        self::getContainer()->set(RegistrationPolicy::class, new class implements RegistrationPolicy {
            public function isOpen(): bool
            {
                return false;
            }
        });

        $this->call('POST', '/api/auth/register', ['firstName' => 'Alex', 'email' => 'alex@example.com', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.registration_closed');
    }

    public function testSigningOutAndBackIn(): void
    {
        $this->register();

        $this->call('POST', '/api/auth/sign-out');
        self::assertResponseStatusCodeSame(204);
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('shared.not_authenticated');

        $this->signIn('ALEX@example.com');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['twoFactorRequired' => false, 'account' => ['email' => 'alex@example.com', 'twoFactorEnabled' => false]]);
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testSigningOutTwiceIsHarmless(): void
    {
        $this->call('POST', '/api/auth/sign-out');

        self::assertResponseStatusCodeSame(204);
    }

    public function testWrongPasswordAndUnknownEmailAnswerTheSame(): void
    {
        $this->register();
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn('alex@example.com', 'not the password');
        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('identity.invalid_credentials');

        $this->signIn('nobody@example.com');
        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('identity.invalid_credentials');
    }

    public function testRepeatedFailuresOnOneAccountAreThrottled(): void
    {
        $this->client->disableReboot();
        $this->register();
        $this->call('POST', '/api/auth/sign-out');

        for ($i = 0; $i < 5; ++$i) {
            $this->signIn('alex@example.com', 'guess number '.$i);
            self::assertResponseStatusCodeSame(401);
        }

        $response = $this->signIn();
        self::assertResponseStatusCodeSame(429);
        self::assertArrayHasKey('retry-after', $response->getHeaders(false));
    }

    public function testProtectedEndpointsRequireASignedInDevice(): void
    {
        foreach ([['GET', '/api/me'], ['GET', '/api/me/sessions'], ['PUT', '/api/me/profile'], ['POST', '/api/me/password'], ['POST', '/api/me/email-verification']] as [$method, $uri]) {
            $this->call($method, $uri, 'GET' === $method ? null : []);
            self::assertResponseStatusCodeSame(401, $method.' '.$uri);
        }
    }

    public function testStateChangingRequestsFromAnotherSiteAreRejected(): void
    {
        $this->call('POST', '/api/auth/sign-in', ['email' => 'alex@example.com', 'password' => self::PASSWORD], ['Origin' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(403);

        $this->call('POST', '/api/auth/sign-in', ['email' => 'alex@example.com', 'password' => self::PASSWORD], ['Sec-Fetch-Site' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);

        $this->call('POST', '/api/auth/sign-in', ['email' => 'nobody@example.com', 'password' => self::PASSWORD], ['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin']);
        self::assertResponseStatusCodeSame(401);
    }
}
