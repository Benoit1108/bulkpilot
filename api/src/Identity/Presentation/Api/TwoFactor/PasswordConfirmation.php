<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use Symfony\Component\Validator\Constraints as Assert;

final class PasswordConfirmation
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096)]
    public string $password = '';
}
