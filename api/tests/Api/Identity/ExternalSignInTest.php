<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Tests\Support\Api\IdentityApiTestCase;

/**
 * Full redirect flow against the fake provider (dev/test only).
 */
final class ExternalSignInTest extends IdentityApiTestCase
{
    public function testSigningInCreatesTheAccountAndReturnsToTheRequestedPage(): void
    {
        self::assertSame('/training', $this->completeFlow('oauth@example.com', '/training'));

        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => 'oauth@example.com', 'emailVerified' => true, 'hasPassword' => false]);
        self::assertCount(1, $this->listSessions(), 'the device is recorded');
    }

    public function testTheProvidersOfferedArePublic(): void
    {
        $this->call('GET', '/api/auth/providers');

        self::assertResponseIsSuccessful();
        self::assertJsonEquals(['providers' => ['fake']]);
    }

    public function testATamperedStateIsRejected(): void
    {
        $location = $this->start('oauth@example.com');

        $this->client->request('GET', str_replace('state=', 'state=x', $location));

        self::assertResponseRedirects('/connexion?oauth_error=identity.external_sign_in_failed');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testACallbackWithoutStartingIsRejected(): void
    {
        $this->client->request('GET', '/api/auth/oauth/fake/callback?state=abc&code=def');

        self::assertResponseRedirects('/connexion?oauth_error=identity.external_sign_in_failed');
    }

    public function testAnUnknownProviderIsUnavailable(): void
    {
        $this->client->request('GET', '/api/auth/oauth/google/start');

        self::assertResponseRedirects('/connexion?oauth_error=identity.external_provider_unavailable');
    }

    public function testAnUnverifiedLocalAccountIsNotTakenOver(): void
    {
        $this->register('taken@example.com');
        $this->call('POST', '/api/auth/sign-out');

        self::assertSame('/connexion?oauth_error=identity.external_account_conflict', $this->completeFlow('taken@example.com'));
    }

    public function testExternalRedirectsAreNeverFollowed(): void
    {
        self::assertSame('/', $this->completeFlow('oauth@example.com', 'https://evil.example'));
    }

    public function testTheSecondFactorStillApplies(): void
    {
        $this->client->disableReboot();
        $this->completeFlow('oauth@example.com');
        $this->call('POST', '/api/auth/sign-out');

        // Enable two-factor through the database state: the flow itself is covered by TwoFactorTest.
        self::getContainer()->get('doctrine.dbal.default_connection')->executeStatement("UPDATE users SET two_factor_secret = 'v1:x' WHERE email = 'oauth@example.com'");

        self::assertSame('/connexion?second_factor=1&redirect=%2F', $this->completeFlow('oauth@example.com'));
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'not signed in before the second factor');
    }

    public function testAProviderRefusalIsReportedAsCancelled(): void
    {
        $location = $this->start('oauth@example.com');
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        \assert(\is_string($query['state'] ?? null));

        $response = $this->client->request('GET', '/api/auth/oauth/fake/callback?'.http_build_query(['state' => $query['state'], 'error' => 'access_denied']));

        self::assertSame('/connexion?oauth_error=identity.external_sign_in_cancelled', $this->locationOf($response));
    }

    public function testAVerifiedAccountIsLinkedAndItsOtherDevicesAreSignedOut(): void
    {
        $this->registerVerified('alex@example.com');
        $this->useDevice('phone');

        self::assertSame('/', $this->completeFlow('alex@example.com'));
        $this->call('GET', '/api/me');
        self::assertJsonContains(['email' => 'alex@example.com', 'hasPassword' => true]);

        $this->useDevice('laptop');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'a device signed in before the link is signed out');
    }

    public function testAnAccountCreatedByAProviderHasNoPasswordUntilItIsReset(): void
    {
        $this->completeFlow('oauth@example.com');
        $this->call('POST', '/api/auth/sign-out');

        $this->signIn('oauth@example.com', 'any password at all');
        self::assertResponseStatusCodeSame(401);
        self::assertProblemCode('identity.invalid_credentials');

        $this->call('POST', '/api/auth/password-reset-request', ['email' => 'oauth@example.com']);
        $this->call('POST', '/api/auth/password-reset', ['token' => $this->tokenFromLastEmail(), 'newPassword' => 'a brand new passphrase']);
        self::assertResponseStatusCodeSame(204);

        $this->signIn('oauth@example.com', 'a brand new passphrase');
        self::assertResponseIsSuccessful();
    }

    public function testAnAccountWithoutAPasswordIsDeletedAfterAFreshSignInAndSigningInAgainKeepsIt(): void
    {
        $this->completeFlow('oauth@example.com', '/training');

        $this->call('POST', '/api/me/deletion', ['password' => '']);
        self::assertResponseStatusCodeSame(202, 'just signed in with the provider');
        $this->call('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);

        self::assertSame('/training?suppression_annulee=1', $this->completeFlow('oauth@example.com', '/training'), 'told once, on the page it lands on');
        $this->call('GET', '/api/me');
        self::assertResponseIsSuccessful();
        $this->call('POST', '/api/auth/sign-out');
        self::assertSame('/training', $this->completeFlow('oauth@example.com', '/training'));
    }

    public function testAnAccountWithoutAPasswordSignedInLongAgoMustSignInAgainToDelete(): void
    {
        $this->completeFlow('oauth@example.com');
        $this->client->getContainer()->get(\Doctrine\DBAL\Connection::class)->executeStatement("UPDATE user_sessions SET opened_at = opened_at - INTERVAL '11 minutes'");

        $this->call('POST', '/api/me/deletion', ['password' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertProblemCode('identity.reauthentication_required');
    }

    public function testTrickRedirectsAreNeverFollowed(): void
    {
        foreach (['//evil.example', '/\\evil.example', "/\t/evil.example", '/%09/evil.example'] as $i => $redirect) {
            $this->useDevice('device'.$i);
            $location = $this->completeFlow(\sprintf('trick%d@example.com', $i), $redirect);
            self::assertContains($location, ['/', '/%09/evil.example'], $redirect);
        }
    }

    private function start(string $email, string $redirect = '/'): string
    {
        $location = $this->locationOf($this->client->request('GET', '/api/auth/oauth/fake/start?'.http_build_query(['login_hint' => $email, 'redirect' => $redirect])));
        self::assertStringStartsWith('https://localhost/api/auth/oauth/fake/callback?', $location);

        return $location;
    }

    private function completeFlow(string $email, string $redirect = '/'): string
    {
        return $this->locationOf($this->client->request('GET', $this->start($email, $redirect)));
    }

    private function locationOf(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
    {
        self::assertSame(302, $response->getStatusCode());
        $location = $response->getHeaders(false)['location'][0] ?? null;
        self::assertIsString($location);

        return $location;
    }
}
