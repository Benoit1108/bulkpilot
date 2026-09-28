<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'PasswordResetRequest',
    operations: [
        new Post(
            uriTemplate: '/auth/password-reset-request',
            status: 202,
            description: 'Emails a reset link if a verified account uses this address. Always 202: accounts are never revealed.',
            output: false,
            processor: PasswordResetRequestProcessor::class,
        ),
    ],
)]
final class PasswordResetRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 254)]
    public string $email = '';
}
