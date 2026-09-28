<?php

declare(strict_types=1);

namespace App\System\Application\Health;

use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<HealthReport>
 */
final readonly class GetHealth implements Query
{
}
