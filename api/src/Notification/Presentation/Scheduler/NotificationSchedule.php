<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Scheduler;

use App\Notification\Application\Check\RunScheduledChecks;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The checks every quarter of an hour (ADR-0029), consumed by the worker (`scheduler_notifications`).
 */
#[AsSchedule('notifications')]
final readonly class NotificationSchedule implements ScheduleProviderInterface
{
    public const string EVERY = '15 minutes';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(self::EVERY, new RunScheduledChecks()))
            // After a restart, one missed run is enough. The state and the lock are the worker's
            // own (local cache, flock): run exactly one worker (docs/DEPLOYMENT.md).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_notifications'));
    }
}
