<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A service the use case relies on is out of reach for now (an external database…): try again later.
 */
abstract class Unavailable extends DomainException
{
}
