<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The caller could not be authenticated.
 */
abstract class AuthenticationFailed extends DomainException
{
}
