<?php

declare(strict_types=1);

namespace App\System\Application\Backup;

use App\Shared\Application\Bus\Job\JobHandler;

/**
 * The schedule's backups and test restores; a failure is logged by Backups and fails the message
 * (retried, then kept in the `failed` queue for the operator).
 */
final readonly class RunScheduledBackups implements JobHandler
{
    public function __construct(private Backups $backups)
    {
    }

    public function __invoke(CreateBackup|VerifyBackup $job): void
    {
        $job instanceof CreateBackup ? $this->backups->create() : $this->backups->verify();
    }
}
