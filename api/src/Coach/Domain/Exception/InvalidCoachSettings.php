<?php

declare(strict_types=1);

namespace App\Coach\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCoachSettings extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'coach.invalid_settings';
    }
}
