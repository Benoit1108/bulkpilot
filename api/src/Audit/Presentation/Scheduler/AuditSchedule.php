<?php

declare(strict_types=1);

namespace App\Audit\Presentation\Scheduler;

use App\Audit\Application\Retention\PurgeAuditEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The retention of the audit journal, once a day, consumed by the worker (`scheduler_audit`).
 */
#[AsSchedule('audit')]
final readonly class AuditSchedule implements ScheduleProviderInterface
{
    public const string EVERY = '1 day';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(self::EVERY, new PurgeAuditEvents()))
            // Local state and lock: exactly one worker (docs/DEPLOYMENT.md).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_audit'));
    }
}
