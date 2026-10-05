<?php

declare(strict_types=1);

namespace App\System\Presentation\Worker;

use App\System\Application\Monitoring\Heartbeats;
use App\System\Domain\Monitoring\Beat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

/**
 * The worker's own signs of life (docs/MONITORING.md):
 * - it beats as soon as it starts, so that a redeployment (a new worker) is not taken for a late
 *   schedule before its first 15-minute tick;
 * - at every loop it touches a file whose age the container healthcheck reads: a consumer that
 *   crashed or hangs stops touching it (a process alone proves nothing).
 */
final readonly class WorkerLiveness
{
    public function __construct(
        private Heartbeats $heartbeats,
        #[Autowire('%kernel.project_dir%/var/worker.alive')]
        private string $aliveFile,
    ) {
    }

    #[AsEventListener]
    public function started(WorkerStartedEvent $event): void
    {
        $this->touch();
        $this->heartbeats->beat(Beat::Scheduler);
    }

    #[AsEventListener]
    public function running(WorkerRunningEvent $event): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        // Best effort: a read-only var/ must not stop the worker (the healthcheck then says so).
        if (is_writable(\dirname($this->aliveFile))) {
            touch($this->aliveFile);
        }
    }
}
