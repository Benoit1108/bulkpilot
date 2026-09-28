<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Policy;

use App\Identity\Application\Port\RegistrationPolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * REGISTRATION_ENABLED: open in development, closed in production once the owner's account exists.
 */
final readonly class EnvironmentRegistrationPolicy implements RegistrationPolicy
{
    public function __construct(
        #[Autowire(env: 'bool:REGISTRATION_ENABLED')]
        private bool $enabled,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->enabled;
    }
}
