<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Account\GetAccount;
use App\Identity\Application\External\ExternalIdentityProviders;
use App\Identity\Application\External\FindExternalIdentityOwner;
use App\Identity\Application\External\SignInWithExternalIdentity;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\Identifier\UuidGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Browser redirects of the OpenID Connect flow (not JSON endpoints). The outcome is handed back
 * to the PWA through the redirect: home, the second-factor step, or an error code to display.
 */
#[AsController]
final readonly class ExternalSignInController
{
    private const string SESSION_KEY = 'identity.external_sign_in';

    public function __construct(
        private ExternalIdentityProviders $providers,
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private DeviceSignIn $deviceSignIn,
        private PendingSignIn $pendingSignIn,
        private UuidGenerator $uuids,
        private RateLimits $rateLimits,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $appUrl,
    ) {
    }

    #[Route('/api/auth/oauth/{provider}/start', name: 'identity_external_start', methods: ['GET'])]
    public function start(string $provider, Request $request): RedirectResponse
    {
        $adapter = $this->providers->get($provider);
        if (null === $adapter) {
            return $this->toPwa('/connexion', ['oauth_error' => 'identity.external_provider_unavailable']);
        }
        $this->rateLimits->consumeForClient('sign_in_ip');

        $state = self::random();
        $verifier = self::random();
        $request->getSession()->set(self::SESSION_KEY, [
            'provider' => $provider,
            'state' => $state,
            'verifier' => $verifier,
            'redirect' => SafeRedirect::path($request->query->getString('redirect')),
        ]);

        $hint = $request->query->getString('login_hint');

        return new RedirectResponse($adapter->authorizationUrl($state, self::challenge($verifier), $this->callbackUrl($provider), '' === $hint ? null : $hint));
    }

    #[Route('/api/auth/oauth/{provider}/callback', name: 'identity_external_callback', methods: ['GET'])]
    public function callback(string $provider, Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $pending = $session->get(self::SESSION_KEY);
        $session->remove(self::SESSION_KEY);

        $adapter = $this->providers->get($provider);
        if (!\is_array($pending) || null === $adapter || ($pending['provider'] ?? null) !== $provider
            || !\is_string($pending['state'] ?? null) || !hash_equals($pending['state'], $request->query->getString('state'))
            || !\is_string($pending['verifier'] ?? null)) {
            return $this->toPwa('/connexion', ['oauth_error' => 'identity.external_sign_in_failed']);
        }
        if ($request->query->has('error')) {
            return $this->toPwa('/connexion', ['oauth_error' => 'identity.external_sign_in_cancelled']);
        }

        try {
            $profile = $adapter->fetchProfile($request->query->getString('code'), $pending['verifier'], $this->callbackUrl($provider));
            $this->commandBus->dispatch(new SignInWithExternalIdentity($profile, $this->uuids->generate()));
            $userId = $this->queryBus->ask(new FindExternalIdentityOwner($profile->provider, $profile->subject));
        } catch (DomainException $e) {
            return $this->toPwa('/connexion', ['oauth_error' => $e->errorCode()]);
        }

        $redirect = \is_string($pending['redirect'] ?? null) ? $pending['redirect'] : '/';

        // The second factor still applies: a provider only replaces the password.
        if ($this->queryBus->ask(new GetAccount($userId))->twoFactorEnabled) {
            $this->pendingSignIn->start($userId);

            return $this->toPwa('/connexion', ['second_factor' => '1', 'redirect' => $redirect]);
        }

        $this->deviceSignIn->signIn($userId);

        return $this->toPwa($redirect);
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim($this->appUrl, '/').'/api/auth/oauth/'.rawurlencode($provider).'/callback';
    }

    /**
     * @param array<string, string> $query
     */
    private function toPwa(string $path, array $query = []): RedirectResponse
    {
        return new RedirectResponse($path.([] === $query ? '' : '?'.http_build_query($query)));
    }

    private static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
