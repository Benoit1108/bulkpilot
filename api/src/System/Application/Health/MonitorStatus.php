<?php

declare(strict_types=1);

namespace App\System\Application\Health;

/**
 * Where a watched activity stands (docs/MONITORING.md).
 */
enum MonitorStatus: string
{
    case Ok = 'ok';
    /** Should have happened by now. */
    case Late = 'late';
    /** Never seen since the database exists (a fresh deployment: until the first run). */
    case Unknown = 'unknown';
    /** A resource is running out. */
    case Low = 'low';
    /** Not expected here (backups off in development). */
    case Disabled = 'disabled';
    /** Not due yet: the first one comes on its schedule (the weekly check after the first backups). */
    case Pending = 'pending';

    public function isFine(): bool
    {
        return \in_array($this, [self::Ok, self::Disabled, self::Pending], true);
    }
}
