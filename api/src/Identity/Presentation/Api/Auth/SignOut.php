<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'SignOut',
    operations: [
        new Post(
            uriTemplate: '/auth/sign-out',
            status: 204,
            description: 'Signs this device out (idempotent).',
            input: false,
            output: false,
            read: false,
            deserialize: false,
            validate: false,
            processor: SignOutProcessor::class,
        ),
    ],
)]
final class SignOut
{
}
