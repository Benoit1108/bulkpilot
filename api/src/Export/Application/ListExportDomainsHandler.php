<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Application\PersonalData\PersonalData;

final readonly class ListExportDomainsHandler implements QueryHandler
{
    public function __construct(private ExportDomains $domains)
    {
    }

    /**
     * @return list<ExportDomainView> in order
     */
    public function __invoke(ListExportDomains $query): array
    {
        return array_map(static fn (PersonalData $source): ExportDomainView => new ExportDomainView($source->domain(), (string) $source->title(), $source->periodFiltered()), $this->domains->exportable());
    }
}
