<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

/**
 * External providers the PWA may offer on the sign-in page (none unless configured).
 */
#[ApiResource(
    shortName: 'SignInProviders',
    operations: [new Get(uriTemplate: '/auth/providers', description: 'Enabled external sign-in providers.', provider: SignInProvidersProvider::class)],
)]
final readonly class SignInProviders
{
    /**
     * @param list<string> $providers
     */
    public function __construct(
        #[ApiProperty(required: true, schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['google', 'apple', 'fake']]])]
        public array $providers,
    ) {
    }
}
