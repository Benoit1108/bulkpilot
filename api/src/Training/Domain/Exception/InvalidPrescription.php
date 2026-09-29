<?php

declare(strict_types=1);

namespace App\Training\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Sets, reps, RIR, rest or tempo out of their bounds.
 */
final class InvalidPrescription extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'training.invalid_prescription';
    }
}
