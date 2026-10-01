<?php

declare(strict_types=1);

namespace App\System\Presentation\Scheduler;

use App\System\Application\Backup\CreateBackup;
use App\System\Application\Backup\VerifyBackup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * A backup every night and a test restore every week (spec §106, ADR-0031), consumed by the
 * worker (`scheduler_backups`). Nothing is scheduled while backups are off (development).
 */
#[AsSchedule('backups')]
final readonly class BackupSchedule implements ScheduleProviderInterface
{
    /** UTC: at night in Europe, after its nightly Coach analyses (03:00 local). */
    public const string BACKUP_AT = 'today 02:30';
    public const string VERIFY_AT = 'last sunday 04:30';

    public function __construct(
        private CacheInterface $cache,
        private LockFactory $locks,
        #[Autowire('%env(bool:BACKUP_ENABLED)%')]
        private bool $enabled,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule()
            // One missed run is enough after a restart; state and lock are the worker's own (one worker).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_backups'));
        if (!$this->enabled) {
            return $schedule;
        }
        $utc = new \DateTimeZone('UTC');

        return $schedule
            // Jobs: handled on the job bus (no transaction around a dump and its upload).
            ->add(RecurringMessage::every('1 day', self::job(new CreateBackup()), new \DateTimeImmutable(self::BACKUP_AT, $utc)))
            ->add(RecurringMessage::every('1 week', self::job(new VerifyBackup()), new \DateTimeImmutable(self::VERIFY_AT, $utc)));
    }

    private static function job(object $job): Envelope
    {
        return new Envelope($job, [new BusNameStamp('job.bus')]);
    }
}
