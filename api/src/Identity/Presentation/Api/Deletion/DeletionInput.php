<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Deletion;

use Symfony\Component\Validator\Constraints as Assert;

final class DeletionInput
{
    /** Empty for an account without a password (created with a provider): a recent sign-in stands for it. */
    #[Assert\Length(max: 4096)]
    public string $password = '';

    /** The authenticator code or a recovery code, required when two-factor authentication is on. */
    #[Assert\Length(max: 64)]
    public ?string $code = null;
}
