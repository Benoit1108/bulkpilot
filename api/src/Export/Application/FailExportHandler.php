<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class FailExportHandler implements CommandHandler
{
    public function __construct(private ExportRequestRepository $exports)
    {
    }

    public function __invoke(FailExport $command): void
    {
        $export = $this->exports->find($command->exportId);
        if (null !== $export) {
            $export->fail();
            $this->exports->save($export);
        }
    }
}
