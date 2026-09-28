<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Application\External\ExternalProfile;
use App\Identity\Application\External\ExternalSignInFailed;
use App\Identity\Domain\External\IdentityProvider;

/**
 * OpenID Connect authorization-code flow with PKCE, run server-side: the browser never sees tokens.
 */
interface ExternalIdentityProvider
{
    public function provider(): IdentityProvider;

    /** Configured (credentials present) and allowed in this environment. */
    public function isEnabled(): bool;

    /**
     * @param string|null $loginHint account to preselect at the provider (OIDC login_hint)
     */
    public function authorizationUrl(string $state, string $codeChallenge, string $redirectUri, ?string $loginHint = null): string;

    /**
     * @throws ExternalSignInFailed
     */
    public function fetchProfile(string $code, string $codeVerifier, string $redirectUri): ExternalProfile;
}
