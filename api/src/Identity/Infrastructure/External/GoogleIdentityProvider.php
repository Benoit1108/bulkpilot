<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\External;

use App\Identity\Application\External\ExternalProfile;
use App\Identity\Application\External\ExternalSignInFailed;
use App\Identity\Application\Port\ExternalIdentityProvider;
use App\Identity\Domain\External\IdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google OpenID Connect. Enabled when GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set.
 * The profile comes from the userinfo endpoint over TLS, with the access token just obtained
 * from Google for this code: no token is ever exposed to the browser.
 */
final readonly class GoogleIdentityProvider implements ExternalIdentityProvider
{
    private const string AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const string TOKEN = 'https://oauth2.googleapis.com/token';
    private const string USERINFO = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'GOOGLE_CLIENT_SECRET')]
        #[\SensitiveParameter]
        private string $clientSecret,
    ) {
    }

    public function provider(): IdentityProvider
    {
        return IdentityProvider::Google;
    }

    public function isEnabled(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri, ?string $loginHint = null): string
    {
        return self::AUTHORIZE.'?'.http_build_query(array_filter([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
            'login_hint' => $loginHint,
        ], static fn (?string $value): bool => null !== $value));
    }

    public function fetchProfile(string $code, string $codeVerifier, string $redirectUri): ExternalProfile
    {
        try {
            $token = $this->httpClient->request('POST', self::TOKEN, ['body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code_verifier' => $codeVerifier,
            ]])->toArray();
            $accessToken = $token['access_token'] ?? null;
            if (!\is_string($accessToken)) {
                throw ExternalSignInFailed::because('no access token');
            }

            $info = $this->httpClient->request('GET', self::USERINFO, ['auth_bearer' => $accessToken])->toArray();
        } catch (HttpException $e) {
            throw ExternalSignInFailed::because('Google rejected the authorization ('.$e->getMessage().')');
        }

        $subject = $info['sub'] ?? null;
        $email = $info['email'] ?? null;
        if (!\is_string($subject) || '' === $subject || !\is_string($email)) {
            throw ExternalSignInFailed::because('incomplete Google profile');
        }
        $firstName = $info['given_name'] ?? null;

        return new ExternalProfile(IdentityProvider::Google, $subject, $email, true === ($info['email_verified'] ?? false), \is_string($firstName) ? $firstName : null);
    }
}
