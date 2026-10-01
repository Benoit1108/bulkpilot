<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\Exception\ExportInProgress;
use App\Export\Domain\Exception\InvalidExport;
use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Job\JobBus;
use Psr\Clock\ClockInterface;

/**
 * Records the request and hands the archive to the worker (asynchronous: it reads every context).
 * One export at a time.
 */
final readonly class RequestExportHandler implements CommandHandler
{
    public function __construct(
        private ExportRequestRepository $exports,
        private ExportDomains $domains,
        private JobBus $jobs,
        private AuditTrail $audit,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestExport $command): void
    {
        $unknown = array_diff($command->domains, array_keys($this->domains->titles()));
        if ([] !== $unknown) {
            throw InvalidExport::because(\sprintf('unknown domain "%s"', implode('", "', $unknown)));
        }
        if ($this->exports->hasOpen($command->userId)) {
            throw ExportInProgress::because('an export is already being made');
        }
        $now = $this->clock->now();
        $export = ExportRequest::request($command->exportId, $command->userId, $command->from, $command->to, $command->domains, $now);
        $this->exports->save($export);
        $this->audit->record($command->userId, 'export.requested', [
            'from' => $export->fromDay()?->format('Y-m-d'),
            'to' => $export->toDay()?->format('Y-m-d'),
            'domains' => [] === $export->domains() ? 'all' : implode(',', $export->domains()),
        ], 'user', $now);
        $this->jobs->dispatch(new GenerateExport($command->exportId));
    }
}
