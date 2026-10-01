<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Media\Archives;
use Psr\Clock\ClockInterface;

final readonly class CompleteExportHandler implements CommandHandler
{
    public const string CONTENT_TYPE = 'application/zip';

    public function __construct(private ExportRequestRepository $exports, private Archives $archives, private ClockInterface $clock)
    {
    }

    public function __invoke(CompleteExport $command): void
    {
        $export = $this->exports->find($command->exportId) ?? throw new ExportTaken();
        $this->archives->store($export->ownerId(), $command->archiveId, $command->path, self::CONTENT_TYPE);
        $export->ready($command->archiveId, $this->clock->now());
        $this->exports->save($export);
    }
}
