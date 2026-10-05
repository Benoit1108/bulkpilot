<?php

declare(strict_types=1);

namespace App\System\Domain\Monitoring;

/**
 * Something that must happen regularly for the service to be fine, and how long its absence is
 * tolerated before the monitoring says it is late (docs/MONITORING.md).
 */
enum Beat: string
{
    /** The worker runs its schedules every 15 minutes. */
    case Scheduler = 'scheduler';
    /** A backup every night. */
    case Backup = 'backup';
    /** A test restore every week. */
    case BackupVerify = 'backup_verify';

    /** A beat older than this is late: twice the period, and a margin for a slow run. */
    public function toleratedMinutes(): int
    {
        return match ($this) {
            self::Scheduler => 40,
            self::Backup => 26 * 60,
            self::BackupVerify => 8 * 24 * 60,
        };
    }

    public function isLate(\DateTimeImmutable $last, \DateTimeImmutable $now): bool
    {
        return $last < $now->modify(\sprintf('-%d minutes', $this->toleratedMinutes()));
    }
}
