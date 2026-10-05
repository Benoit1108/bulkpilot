<?php

declare(strict_types=1);

namespace App\System\Application\Health;

final readonly class HealthReport
{
    /**
     * @param array<non-empty-string, bool>          $checks   what a request needs (readiness)
     * @param array<non-empty-string, MonitorStatus> $monitors what the monitoring watches, when asked
     */
    public function __construct(public array $checks, public array $monitors = [])
    {
    }

    public function isHealthy(): bool
    {
        return !\in_array(false, $this->checks, true)
            && [] === array_filter($this->monitors, static fn (MonitorStatus $status): bool => !$status->isFine());
    }
}
