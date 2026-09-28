<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Account;

use Symfony\Component\Validator\Constraints as Assert;

final class SecondFactorInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $code = '';
}
