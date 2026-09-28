<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Query;

/**
 * Marker for read use cases. Implementations expose a single
 * `__invoke(SomeQuery $query): SomeResult` method.
 */
interface QueryHandler
{
}
