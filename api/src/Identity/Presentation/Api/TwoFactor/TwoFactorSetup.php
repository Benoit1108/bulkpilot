<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'TwoFactorSetup',
    operations: [
        new Post(
            uriTemplate: '/me/two-factor/setup',
            status: 200,
            description: 'Starts enrolling an authenticator app (password required, email must be verified).',
            input: PasswordConfirmation::class,
            processor: TwoFactorSetupProcessor::class,
        ),
    ],
)]
final readonly class TwoFactorSetup
{
    public function __construct(
        /** Base32 seed, for manual entry. */
        #[ApiProperty(required: true)]
        public string $secret,
        /** otpauth:// URI to render as a QR code. */
        #[ApiProperty(required: true)]
        public string $otpauthUri,
    ) {
    }
}
