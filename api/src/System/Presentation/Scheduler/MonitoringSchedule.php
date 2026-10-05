<?php

declare(strict_types=1);

namespace App\System\Presentation\Scheduler;

use App\System\Application\Monitoring\RecordSchedulerBeat;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The worker's heartbeat (docs/MONITORING.md): every 15 minutes it records that its schedules
 * run, so that a stopped or stuck worker shows up in the health report and in Uptime Kuma.
 */
#[AsSchedule('monitoring')]
final readonly class MonitoringSchedule implements ScheduleProviderInterface
{
    public const string EVERY = '15 minutes';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_monitoring'))
            // A job: written outside any transaction, like the backups.
            ->add(RecurringMessage::every(self::EVERY, new Envelope(new RecordSchedulerBeat(), [new BusNameStamp('job.bus')])));
    }
}
