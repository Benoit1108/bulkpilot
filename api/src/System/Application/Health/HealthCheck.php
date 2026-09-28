<?php

declare(strict_types=1);

namespace App\System\Application\Health;

/**
 * Port implemented by each infrastructure dependency the API needs to serve requests.
 */
interface HealthCheck
{
    /**
     * @return non-empty-string
     */
    public function name(): string;

    public function isHealthy(): bool;
}
