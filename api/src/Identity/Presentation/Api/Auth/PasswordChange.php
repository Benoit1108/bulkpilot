<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Identity\Domain\User\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'PasswordChange',
    operations: [
        new Post(
            uriTemplate: '/me/password',
            status: 204,
            description: 'Changes the password; every other device is signed out.',
            output: false,
            processor: PasswordChangeProcessor::class,
        ),
    ],
)]
final class PasswordChange
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096)]
    public string $currentPassword = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public string $newPassword = '';
}
