<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Application\Media\Archives;
use Psr\Clock\ClockInterface;

final readonly class GetExportLinkHandler implements QueryHandler
{
    public function __construct(private ExportRequestRepository $exports, private Archives $archives, private ClockInterface $clock)
    {
    }

    public function __invoke(GetExportLink $query): string
    {
        $export = $this->exports->getOwnedBy($query->exportId, $query->userId);
        $archiveId = $export->archive($this->clock->now());

        return $this->archives->downloadLink($query->userId, $archiveId, \sprintf('bulkpilot-export-%s.zip', $export->requestedAt()->format('Y-m-d')), GetExportLink::LINK_SECONDS);
    }
}
