<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class StartExportHandler implements CommandHandler
{
    public function __construct(private ExportRequestRepository $exports)
    {
    }

    /**
     * @throws ExportTaken
     */
    public function __invoke(StartExport $command): void
    {
        $export = $this->exports->find($command->exportId);
        if (null === $export || !$export->start()) {
            throw new ExportTaken();
        }
        $this->exports->save($export);
    }
}
