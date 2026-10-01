<?php

declare(strict_types=1);

namespace App\Audit\Application\Retention;

use App\Shared\Application\Bus\Command\Command;

/**
 * Removes the events older than the retention (`AUDIT_RETENTION_DAYS`), once a day.
 */
final readonly class PurgeAuditEvents implements Command
{
}
