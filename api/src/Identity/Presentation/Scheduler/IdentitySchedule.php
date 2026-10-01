<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Scheduler;

use App\Identity\Application\Deletion\EraseDueAccounts;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Erasure of the accounts whose deletion delay is over (ADR-0030), consumed by the worker
 * (`scheduler_identity`): within the hour after the 7 days.
 */
#[AsSchedule('identity')]
final readonly class IdentitySchedule implements ScheduleProviderInterface
{
    public const string EVERY = '1 hour';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(self::EVERY, new EraseDueAccounts()))
            // Local state and lock: exactly one worker (docs/DEPLOYMENT.md).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_identity'));
    }
}
