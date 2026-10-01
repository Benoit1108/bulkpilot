<?php

declare(strict_types=1);

namespace App\Export\Presentation\Scheduler;

use App\Export\Application\PurgeExpiredExports;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Deletes the archives past their day and fails the exports whose worker died (ADR-0030), consumed by the worker (`scheduler_export`).
 */
#[AsSchedule('export')]
final readonly class ExportSchedule implements ScheduleProviderInterface
{
    public const string EVERY = '15 minutes';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(self::EVERY, new PurgeExpiredExports()))
            // One missed run is enough after a restart; one worker only (docs/DEPLOYMENT.md).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_export'));
    }
}
