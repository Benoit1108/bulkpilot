<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Job;

/**
 * Marker of a job's handler, bound to the job bus (no transaction) in Kernel::build().
 */
interface JobHandler
{
}
