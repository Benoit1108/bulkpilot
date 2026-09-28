<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class EmailNotVerified extends DomainException
{
    public static function create(): self
    {
        return new self('The email address must be verified first.');
    }

    public function errorCode(): string
    {
        return 'identity.email_not_verified';
    }
}
