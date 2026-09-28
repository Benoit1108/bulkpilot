<?php

declare(strict_types=1);

namespace App\Identity\Application\External;

use App\Identity\Domain\External\IdentityProvider;

/**
 * What a provider tells us about the person, after a successful authorization.
 */
final readonly class ExternalProfile
{
    public function __construct(
        public IdentityProvider $provider,
        public string $subject,
        public string $email,
        public bool $emailVerified,
        public ?string $firstName,
    ) {
    }
}
