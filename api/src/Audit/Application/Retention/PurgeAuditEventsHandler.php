<?php

declare(strict_types=1);

namespace App\Audit\Application\Retention;

use App\Audit\Domain\AuditEventRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class PurgeAuditEventsHandler implements CommandHandler
{
    public function __construct(
        private AuditEventRepository $events,
        private ClockInterface $clock,
        private LoggerInterface $auditLogger,
        private int $retentionDays,
    ) {
        if ($retentionDays < 1) {
            throw new \LogicException('The audit retention is at least one day.');
        }
    }

    public function __invoke(PurgeAuditEvents $command): void
    {
        $removed = $this->events->purgeBefore($this->clock->now()->modify(\sprintf('-%d days', $this->retentionDays)));
        $this->auditLogger->info('audit.purged', ['events' => $removed, 'retention_days' => $this->retentionDays]);
    }
}
