<?php

declare(strict_types=1);

namespace App\System\Application\Monitoring;

use App\Shared\Application\Bus\Job\JobHandler;
use App\System\Domain\Monitoring\Beat;

final readonly class RecordSchedulerBeatHandler implements JobHandler
{
    public function __construct(private Heartbeats $heartbeats)
    {
    }

    public function __invoke(RecordSchedulerBeat $job): void
    {
        $this->heartbeats->beat(Beat::Scheduler);
    }
}
