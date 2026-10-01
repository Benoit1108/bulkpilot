<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

use App\Shared\Application\Bus\Job\Job;

/**
 * The nightly backup (spec §106), from the worker's schedule: a job, no transaction around the
 * dump and its upload.
 */
final readonly class CreateBackup implements Job
{
}
