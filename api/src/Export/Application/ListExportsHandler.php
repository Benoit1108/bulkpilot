<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class ListExportsHandler implements QueryHandler
{
    public function __construct(private ExportRequestRepository $exports, private ClockInterface $clock)
    {
    }

    /**
     * @return list<ExportView>
     */
    public function __invoke(ListExports $query): array
    {
        $now = $this->clock->now();

        return array_map(static fn (ExportRequest $export): ExportView => ExportView::of($export, $now), $this->exports->latestOf($query->userId, ListExports::LIMIT));
    }
}
