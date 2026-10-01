<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Exception\DomainException;

/** A programming error: an audited action is always named by the code, never by the user. */
final class InvalidAuditEvent extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'audit.invalid_event';
    }
}
