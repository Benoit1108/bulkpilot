<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Identity\Domain\User\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'PasswordReset',
    operations: [
        new Post(
            uriTemplate: '/auth/password-reset',
            status: 204,
            description: 'Sets a new password from an emailed link and signs every device out.',
            output: false,
            processor: PasswordResetProcessor::class,
        ),
    ],
)]
final class PasswordReset
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public string $token = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public string $newPassword = '';
}
