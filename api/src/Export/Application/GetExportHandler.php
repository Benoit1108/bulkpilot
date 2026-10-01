<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class GetExportHandler implements QueryHandler
{
    public function __construct(private ExportRequestRepository $exports, private ClockInterface $clock)
    {
    }

    public function __invoke(GetExport $query): ExportView
    {
        return ExportView::of($this->exports->getOwnedBy($query->exportId, $query->userId), $this->clock->now());
    }
}
