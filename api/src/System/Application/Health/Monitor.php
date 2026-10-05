<?php

declare(strict_types=1);

namespace App\System\Application\Health;

/**
 * What the monitoring watches beyond readiness (the worker, the backups, the disk): reported by
 * `/api/health?scope=monitoring`, never by the container's readiness probe, which only needs what
 * a request needs.
 */
interface Monitor
{
    /**
     * @return array<non-empty-string, MonitorStatus>
     */
    public function statuses(): array;
}
