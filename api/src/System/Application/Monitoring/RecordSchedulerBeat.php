<?php

declare(strict_types=1);

namespace App\System\Application\Monitoring;

use App\Shared\Application\Bus\Job\Job;

/**
 * Sent by the worker's monitoring schedule: proof that the worker runs its schedules.
 */
final readonly class RecordSchedulerBeat implements Job
{
}
