<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The request conflicts with the current state (e.g. a duplicate).
 */
abstract class Conflict extends DomainException
{
}
