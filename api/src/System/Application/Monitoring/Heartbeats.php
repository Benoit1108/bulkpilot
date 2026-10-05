<?php

declare(strict_types=1);

namespace App\System\Application\Monitoring;

use App\System\Domain\Monitoring\Beat;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * A beat recorded for the health report and told to the external monitor (healthchecks.io), or a
 * failure told. Best effort: the monitoring never fails the work it watches (a backup sent stays a
 * backup sent if the beat cannot be written).
 */
final readonly class Heartbeats
{
    public function __construct(private Beats $beats, private Pings $pings, private ClockInterface $clock, private LoggerInterface $logger)
    {
    }

    public function beat(Beat $beat): void
    {
        try {
            $this->beats->record($beat, $this->clock->now());
        } catch (\Throwable $failure) {
            $this->logger->warning('system.heartbeat_not_recorded', ['beat' => $beat->value, 'reason' => $failure->getMessage()]);
        }
        $this->pings->up($beat);
    }

    public function failed(Beat $beat, string $reason): void
    {
        $this->pings->down($beat, $reason);
    }
}
