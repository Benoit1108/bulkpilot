<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class DownloadExportHandler implements CommandHandler
{
    public function __construct(private ExportRequestRepository $exports, private AuditTrail $audit, private ClockInterface $clock)
    {
    }

    public function __invoke(DownloadExport $command): void
    {
        $now = $this->clock->now();
        $export = $this->exports->getOwnedBy($command->exportId, $command->userId);
        $export->archive($now);
        $this->audit->record($command->userId, 'export.downloaded', ['export' => $export->id()->value], 'user', $now);
    }
}
