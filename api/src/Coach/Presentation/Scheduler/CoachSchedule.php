<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Scheduler;

use App\Coach\Application\Analysis\RunNightlyAnalyses;
use App\Coach\Application\Analysis\RunNightlyAnalysesHandler;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The nightly analyses (ADR-0029), consumed by the worker (`scheduler_coach`). It drives the
 * application like a console command does, hence Presentation. It must tick at least every
 * {@see RunNightlyAnalysesHandler::WINDOW_MINUTES} minutes, or a night could be skipped.
 */
#[AsSchedule('coach')]
final readonly class CoachSchedule implements ScheduleProviderInterface
{
    public const string EVERY = '15 minutes';

    public function __construct(private CacheInterface $cache, private LockFactory $locks)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every(self::EVERY, new RunNightlyAnalyses()))
            // Local state and lock: exactly one worker (docs/DEPLOYMENT.md).
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->locks->createLock('schedule_coach'));
    }
}
