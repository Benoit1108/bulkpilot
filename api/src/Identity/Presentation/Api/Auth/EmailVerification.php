<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'EmailVerification',
    operations: [
        new Post(
            uriTemplate: '/auth/email-verification',
            status: 204,
            description: 'Redeems the link emailed at registration (works signed in or not).',
            output: false,
            processor: EmailVerificationProcessor::class,
        ),
        new Post(
            uriTemplate: '/me/email-verification',
            status: 204,
            description: 'Emails a new verification link; previous links stop working.',
            input: false,
            output: false,
            read: false,
            deserialize: false,
            validate: false,
            processor: ResendEmailVerificationProcessor::class,
        ),
    ],
)]
final class EmailVerification
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $token = '';
}
