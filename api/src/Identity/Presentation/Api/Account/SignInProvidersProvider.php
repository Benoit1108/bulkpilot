<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\External\ExternalIdentityProviders;
use App\Identity\Domain\External\IdentityProvider;

/**
 * @implements ProviderInterface<SignInProviders>
 */
final readonly class SignInProvidersProvider implements ProviderInterface
{
    public function __construct(private ExternalIdentityProviders $registry)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SignInProviders
    {
        return new SignInProviders(array_map(static fn (IdentityProvider $provider): string => $provider->value, $this->registry->enabled()));
    }
}
