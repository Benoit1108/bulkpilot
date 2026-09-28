<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\External\ExternalSignInFailed;
use App\Identity\Domain\External\IdentityProvider;
use App\Identity\Infrastructure\External\GoogleIdentityProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleIdentityProviderTest extends TestCase
{
    public function testItIsDisabledWithoutCredentials(): void
    {
        self::assertFalse(new GoogleIdentityProvider(new MockHttpClient(), '', '')->isEnabled());
        self::assertTrue(new GoogleIdentityProvider(new MockHttpClient(), 'id', 'secret')->isEnabled());
    }

    public function testTheAuthorizationUrlUsesPkceAndTheState(): void
    {
        $url = new GoogleIdentityProvider(new MockHttpClient(), 'client-id', 'secret')->authorizationUrl('the-state', 'the-challenge', 'https://app/cb', 'alex@example.com');

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame(['client_id' => 'client-id', 'redirect_uri' => 'https://app/cb', 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => 'the-state', 'code_challenge' => 'the-challenge', 'code_challenge_method' => 'S256', 'prompt' => 'select_account', 'login_hint' => 'alex@example.com'], $query);
    }

    public function testTheCodeIsExchangedServerSideForTheProfile(): void
    {
        /** @var list<array{string, string, array{body?: string, headers?: list<string>}}> $requests */
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            /** @var array{body?: string, headers?: list<string>} $options */
            $requests[] = [$method, $url, $options];

            return str_contains($url, 'token')
                ? new JsonMockResponse(['access_token' => 'at-123', 'id_token' => 'x'])
                : new JsonMockResponse(['sub' => '1234', 'email' => 'alex@gmail.com', 'email_verified' => true, 'given_name' => 'Alex']);
        });

        $profile = new GoogleIdentityProvider($client, 'client-id', 'secret')->fetchProfile('the-code', 'the-verifier', 'https://app/cb');

        self::assertSame(IdentityProvider::Google, $profile->provider);
        self::assertSame(['1234', 'alex@gmail.com', true, 'Alex'], [$profile->subject, $profile->email, $profile->emailVerified, $profile->firstName]);
        self::assertSame(['POST', 'https://oauth2.googleapis.com/token'], [$requests[0][0], $requests[0][1]]);
        self::assertStringContainsString('code_verifier=the-verifier', $requests[0][2]['body'] ?? '');
        self::assertSame(['GET', 'https://openidconnect.googleapis.com/v1/userinfo'], [$requests[1][0], $requests[1][1]]);
        self::assertContains('Authorization: Bearer at-123', $requests[1][2]['headers'] ?? []);
    }

    public function testARejectedCodeFailsTheSignIn(): void
    {
        $client = new MockHttpClient(new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400]));

        $this->expectException(ExternalSignInFailed::class);
        new GoogleIdentityProvider($client, 'client-id', 'secret')->fetchProfile('bad', 'v', 'https://app/cb');
    }

    public function testAnUnverifiedGoogleEmailIsReportedAsSuch(): void
    {
        $client = new MockHttpClient([new JsonMockResponse(['access_token' => 'at']), new JsonMockResponse(['sub' => '1', 'email' => 'a@b.c'])]);

        self::assertFalse(new GoogleIdentityProvider($client, 'id', 'secret')->fetchProfile('c', 'v', 'https://app/cb')->emailVerified);
    }
}
