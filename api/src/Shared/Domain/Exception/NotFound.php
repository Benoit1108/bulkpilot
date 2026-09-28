<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The requested resource does not exist (or belongs to someone else).
 */
abstract class NotFound extends DomainException
{
}
