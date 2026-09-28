<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use Symfony\Component\Validator\Constraints as Assert;

final class SignInInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 254)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 4096)]
    public string $password = '';
}
