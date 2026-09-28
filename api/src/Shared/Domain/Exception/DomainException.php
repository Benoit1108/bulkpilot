<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Root of every business rule violation.
 *
 * The error code is a stable, machine-readable identifier (e.g. "nutrition.recipe.no_portion")
 * that the presentation layer maps to a user-facing message; the exception message itself
 * is technical and never shown as-is to the user.
 */
abstract class DomainException extends \DomainException
{
    abstract public function errorCode(): string;
}
