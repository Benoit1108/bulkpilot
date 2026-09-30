<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Bus\Command\Command;

/**
 * Sent by the schedule every quarter of an hour (ADR-0029).
 */
final readonly class RunScheduledChecks implements Command
{
}
