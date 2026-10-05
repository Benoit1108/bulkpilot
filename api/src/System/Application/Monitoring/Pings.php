<?php

declare(strict_types=1);

namespace App\System\Application\Monitoring;

use App\System\Domain\Monitoring\Beat;

/**
 * Tells an external monitor (healthchecks.io checks, outside the server, docs/MONITORING.md) that
 * a beat happened, or failed. Best effort: a monitor that cannot be reached never fails the work it watches.
 */
interface Pings
{
    public function up(Beat $beat): void;

    public function down(Beat $beat, string $reason): void;
}
