<?php

declare(strict_types=1);

namespace App\System\Application\Health;

use App\Shared\Application\Bus\Query\Query;

/**
 * @implements Query<HealthReport>
 */
final readonly class GetHealth implements Query
{
    /**
     * @param bool $monitoring the monitors too (Uptime Kuma), not only readiness (the container probe)
     */
    public function __construct(public bool $monitoring = false)
    {
    }
}
