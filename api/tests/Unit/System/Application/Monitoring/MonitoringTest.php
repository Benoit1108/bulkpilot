<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Application\Monitoring;

use App\System\Application\Health\GetHealth;
use App\System\Application\Health\GetHealthHandler;
use App\System\Application\Health\HeartbeatMonitor;
use App\System\Application\Health\MonitorStatus;
use App\System\Application\Monitoring\Beats;
use App\System\Application\Monitoring\Heartbeats;
use App\System\Application\Monitoring\RecordSchedulerBeat;
use App\System\Application\Monitoring\RecordSchedulerBeatHandler;
use App\System\Domain\Monitoring\Beat;
use App\System\Infrastructure\Health\DiskMonitor;
use App\System\Presentation\Scheduler\MonitoringSchedule;
use App\System\Presentation\Worker\WorkerLiveness;
use App\Tests\Support\System\InMemoryBeats;
use App\Tests\Support\System\RecordingLogger;
use App\Tests\Support\System\RecordingPings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * What the monitoring says of the worker, the backups and the disk (docs/MONITORING.md).
 */
final class MonitoringTest extends TestCase
{
    private InMemoryBeats $beats;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->beats = new InMemoryBeats();
        $this->clock = new MockClock('2026-10-02 12:00:00+00:00');
    }

    public function testTheWorkersScheduleBeatsAndTellsTheMonitor(): void
    {
        $pings = new RecordingPings();
        (new RecordSchedulerBeatHandler(new Heartbeats($this->beats, $pings, $this->clock, new RecordingLogger())))(new RecordSchedulerBeat());

        self::assertSame('2026-10-02T12:00:00+00:00', $this->beats->lastOf(Beat::Scheduler)?->format(\DATE_ATOM));
        self::assertSame(['scheduler up'], $pings->sent);
    }

    public function testAWorkerIsLateAfterTwoMissedTicksAndUnknownBeforeItsFirst(): void
    {
        $monitor = new HeartbeatMonitor($this->beats, $this->clock, true);
        self::assertSame(MonitorStatus::Unknown, $monitor->statuses()['worker']);

        $this->beats->record(Beat::Scheduler, $this->clock->now()->modify('-40 minutes'));
        self::assertSame(MonitorStatus::Ok, $monitor->statuses()['worker']);

        $this->beats->record(Beat::Scheduler, $this->clock->now()->modify('-41 minutes'));
        self::assertSame(MonitorStatus::Late, $monitor->statuses()['worker']);
    }

    public function testBackupsAreLateAfterADayAndTheirCheckAfterAWeek(): void
    {
        $this->beats->record(Beat::Backup, $this->clock->now()->modify('-26 hours'));
        $this->beats->record(Beat::BackupVerify, $this->clock->now()->modify('-8 days'));
        $monitor = new HeartbeatMonitor($this->beats, $this->clock, true);
        self::assertSame([MonitorStatus::Ok, MonitorStatus::Ok], [$monitor->statuses()['backup'], $monitor->statuses()['backup_verify']]);

        $this->clock->modify('+1 minute');
        self::assertSame([MonitorStatus::Late, MonitorStatus::Late], [$monitor->statuses()['backup'], $monitor->statuses()['backup_verify']]);
    }

    public function testTheWeeklyCheckIsPendingDuringTheFirstWeekOfBackupsThenUnknown(): void
    {
        $monitor = new HeartbeatMonitor($this->beats, $this->clock, true);
        self::assertSame(MonitorStatus::Unknown, $monitor->statuses()['backup_verify'], 'no backup yet: nothing says one is coming');

        $this->beats->record(Beat::Backup, $this->clock->now());
        self::assertSame(MonitorStatus::Pending, $monitor->statuses()['backup_verify']);
        self::assertTrue(MonitorStatus::Pending->isFine());

        $this->clock->modify('+8 days');
        $this->beats->record(Beat::Backup, $this->clock->now());
        self::assertSame('pending', $monitor->statuses()['backup_verify']->value, 'the grace starts at the first backup, not the latest');
        $this->clock->modify('+1 minute');
        self::assertSame('unknown', $monitor->statuses()['backup_verify']->value);
    }

    public function testABeatThatCannotBeWrittenIsLoggedAndStillPinged(): void
    {
        $broken = new class implements Beats {
            public function record(Beat $beat, \DateTimeImmutable $at): void
            {
                throw new \RuntimeException('database is down');
            }

            public function lastOf(Beat $beat): ?\DateTimeImmutable
            {
                return null;
            }

            public function firstOf(Beat $beat): ?\DateTimeImmutable
            {
                return null;
            }
        };
        $pings = new RecordingPings();
        $logger = new RecordingLogger();

        new Heartbeats($broken, $pings, $this->clock, $logger)->beat(Beat::Backup);

        self::assertSame(['warning system.heartbeat_not_recorded'], $logger->lines());
        self::assertSame(['backup up'], $pings->sent);
    }

    public function testToleranceCoversTwoPeriodsOfEachSchedule(): void
    {
        $period = \DateInterval::createFromDateString(MonitoringSchedule::EVERY);
        self::assertGreaterThanOrEqual(2 * ($period->h * 60 + $period->i), Beat::Scheduler->toleratedMinutes());
        // Nightly and weekly runs (BackupSchedule): a day and a week, plus a margin for a slow run.
        self::assertGreaterThan(24 * 60, Beat::Backup->toleratedMinutes());
        self::assertGreaterThan(7 * 24 * 60, Beat::BackupVerify->toleratedMinutes());
    }

    public function testTheWorkerBeatsWhenItStartsAndTouchesItsFileAtEveryLoop(): void
    {
        $file = sys_get_temp_dir().'/bulkpilot-worker-'.bin2hex(random_bytes(4)).'.alive';
        $pings = new RecordingPings();
        $liveness = new WorkerLiveness(new Heartbeats($this->beats, $pings, $this->clock, new RecordingLogger()), $file);
        $worker = self::createStub(\Symfony\Component\Messenger\Worker::class);

        $liveness->started(new \Symfony\Component\Messenger\Event\WorkerStartedEvent($worker));
        self::assertFileExists($file);
        self::assertSame(['scheduler up'], $pings->sent);
        self::assertNotNull($this->beats->lastOf(Beat::Scheduler));

        touch($file, time() - 600);
        $liveness->running(new \Symfony\Component\Messenger\Event\WorkerRunningEvent($worker, true));
        clearstatcache();
        self::assertGreaterThan(time() - 60, filemtime($file));
        unlink($file);
    }

    public function testNoBackupIsExpectedWhereTheyAreOff(): void
    {
        $statuses = new HeartbeatMonitor($this->beats, $this->clock, false)->statuses();

        self::assertSame([MonitorStatus::Disabled, MonitorStatus::Disabled], [$statuses['backup'], $statuses['backup_verify']]);
    }

    public function testTheDiskIsLowUnderAGibibyteOrATenth(): void
    {
        $disk = static fn (float|false $free, float|false $total): DiskMonitor => new DiskMonitor('/var', static fn (string $directory): array => ['free' => $free, 'total' => $total]);

        self::assertSame(['disk' => MonitorStatus::Ok], $disk(5.0 * 1024 ** 3, 20.0 * 1024 ** 3)->statuses());
        self::assertSame(['disk' => MonitorStatus::Low], $disk(0.9 * 1024 ** 3, 2.0 * 1024 ** 3)->statuses());
        self::assertSame(['disk' => MonitorStatus::Low], $disk(9.0 * 1024 ** 3, 100.0 * 1024 ** 3)->statuses());
        self::assertSame(['disk' => MonitorStatus::Unknown], $disk(false, false)->statuses());
    }

    public function testMonitorsOnlyCountWhenAskedForAndADisabledOneIsFine(): void
    {
        $handler = new GetHealthHandler([], [new HeartbeatMonitor($this->beats, $this->clock, false)]);

        $readiness = $handler(new GetHealth());
        self::assertSame([], $readiness->monitors);
        self::assertTrue($readiness->isHealthy());

        self::assertFalse($handler(new GetHealth(monitoring: true))->isHealthy(), 'the worker never beat');
        $this->beats->record(Beat::Scheduler, $this->clock->now());
        $monitoring = $handler(new GetHealth(monitoring: true));
        self::assertTrue($monitoring->isHealthy());
        self::assertSame(['worker' => MonitorStatus::Ok, 'backup' => MonitorStatus::Disabled, 'backup_verify' => MonitorStatus::Disabled], $monitoring->monitors);
    }
}
