<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class RegistrationClosed extends DomainException
{
    public static function create(): self
    {
        return new self('Registration is closed on this instance.');
    }

    public function errorCode(): string
    {
        return 'identity.registration_closed';
    }
}
