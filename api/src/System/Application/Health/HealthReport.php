<?php

declare(strict_types=1);

namespace App\System\Application\Health;

final readonly class HealthReport
{
    /**
     * @param array<non-empty-string, bool> $checks
     */
    public function __construct(public array $checks)
    {
    }

    public function isHealthy(): bool
    {
        return !\in_array(false, $this->checks, true);
    }
}
