<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'TwoFactorDisabling',
    operations: [
        new Post(
            uriTemplate: '/me/two-factor/disable',
            status: 204,
            description: 'Turns two-factor authentication off (password required).',
            input: PasswordConfirmation::class,
            output: false,
            processor: TwoFactorDisableProcessor::class,
        ),
    ],
)]
final class TwoFactorDisabling
{
}
