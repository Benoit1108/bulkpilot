<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use App\Identity\Domain\User\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 50, normalizer: 'trim')]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 254)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public string $password = '';
}
