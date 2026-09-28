<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Identity\Application\Port\ExternalIdentityProvider;
use App\Identity\Domain\External\IdentityProvider;

/**
 * The enabled providers, by name.
 */
final readonly class ExternalIdentityProviders
{
    /**
     * @param iterable<ExternalIdentityProvider> $providers
     */
    public function __construct(private iterable $providers)
    {
    }

    /**
     * @return list<IdentityProvider>
     */
    public function enabled(): array
    {
        $enabled = [];
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled()) {
                $enabled[] = $provider->provider();
            }
        }

        return $enabled;
    }

    public function get(string $name): ?ExternalIdentityProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->isEnabled() && $provider->provider()->value === $name) {
                return $provider;
            }
        }

        return null;
    }
}
