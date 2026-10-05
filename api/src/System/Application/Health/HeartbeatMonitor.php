<?php

declare(strict_types=1);

namespace App\System\Application\Health;

use App\System\Application\Monitoring\Beats;
use App\System\Domain\Monitoring\Beat;
use Psr\Clock\ClockInterface;

/**
 * The worker's schedules and the backups, from their last beats.
 */
final readonly class HeartbeatMonitor implements Monitor
{
    public function __construct(
        private Beats $beats,
        private ClockInterface $clock,
        /** Off in development: no backup is expected there. */
        private bool $backups,
    ) {
    }

    public function statuses(): array
    {
        return [
            'worker' => $this->status(Beat::Scheduler),
            'backup' => $this->backups ? $this->status(Beat::Backup) : MonitorStatus::Disabled,
            'backup_verify' => $this->backups ? $this->verifyStatus() : MonitorStatus::Disabled,
        ];
    }

    /**
     * The weekly check has not run yet on a fresh database: pending while the first backups are
     * younger than a check period, unknown after it.
     */
    private function verifyStatus(): MonitorStatus
    {
        $status = $this->status(Beat::BackupVerify);
        $firstBackup = $this->beats->firstOf(Beat::Backup);
        if (MonitorStatus::Unknown === $status && null !== $firstBackup && !Beat::BackupVerify->isLate($firstBackup, $this->clock->now())) {
            return MonitorStatus::Pending;
        }

        return $status;
    }

    private function status(Beat $beat): MonitorStatus
    {
        $last = $this->beats->lastOf($beat);

        return match (true) {
            null === $last => MonitorStatus::Unknown,
            $beat->isLate($last, $this->clock->now()) => MonitorStatus::Late,
            default => MonitorStatus::Ok,
        };
    }
}
