<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Root of every business rule violation.
 *
 * The error code is a stable, machine-readable identifier (e.g. "nutrition.recipe.no_portion")
 * that the PWA maps to a user-facing message; the exception message itself is technical.
 *
 * Subclass one of the categories to tell the presentation layer how to answer: NotFound,
 * Conflict, AuthenticationFailed; any other rule violation is a 422.
 */
abstract class DomainException extends \DomainException
{
    abstract public function errorCode(): string;
}
