<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\TwoFactor;

use Symfony\Component\Validator\Constraints as Assert;

final class CodeInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\s*\d{3}\s?\d{3}\s*$/', message: 'Saisis le code à 6 chiffres affiché par ton application.')]
    public string $code = '';
}
