<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidTimeZone extends DomainException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('"%s" is not an IANA time zone.', $name));
    }

    public function errorCode(): string
    {
        return 'identity.invalid_time_zone';
    }
}
