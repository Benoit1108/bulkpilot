<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

use App\Shared\Application\Bus\Job\Job;

/**
 * The weekly test restore (spec §106), from the worker's schedule.
 */
final readonly class VerifyBackup implements Job
{
}
