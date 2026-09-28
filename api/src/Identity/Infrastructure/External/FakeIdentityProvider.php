<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\External;

use App\Identity\Application\External\ExternalProfile;
use App\Identity\Application\External\ExternalSignInFailed;
use App\Identity\Application\Port\ExternalIdentityProvider;
use App\Identity\Domain\External\IdentityProvider;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Local stand-in for an OpenID provider, so the whole flow runs without credentials.
 * Only defined in dev and test: the class is not even registered in production.
 *
 * Its "authorization page" is skipped: the redirect goes straight to our callback with a code
 * carrying the login hint (the email to sign in as) and the PKCE challenge.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class FakeIdentityProvider implements ExternalIdentityProvider
{
    public function provider(): IdentityProvider
    {
        return IdentityProvider::Fake;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri, ?string $loginHint = null): string
    {
        $email = $loginHint ?? 'fake.user@example.com';

        return $redirectUri.'?'.http_build_query(['state' => $state, 'code' => $codeChallenge.'.'.base64_encode($email)]);
    }

    public function fetchProfile(string $code, string $codeVerifier, string $redirectUri): ExternalProfile
    {
        [$challenge, $encodedEmail] = explode('.', $code, 2) + ['', ''];
        $expected = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $email = base64_decode($encodedEmail, true);
        if (!hash_equals($expected, $challenge) || false === $email || '' === $email) {
            throw ExternalSignInFailed::because('PKCE verification failed');
        }

        return new ExternalProfile(IdentityProvider::Fake, 'fake-subject-'.$email, $email, true, 'Fake');
    }
}
