<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Export\Application\ExportDomainView;
use App\Export\Application\ListExportDomains;
use App\Shared\Application\Bus\Query\QueryBus;

/**
 * @implements ProviderInterface<ExportDomain>
 */
final readonly class ExportDomainProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return list<ExportDomain>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(static fn (ExportDomainView $domain): ExportDomain => new ExportDomain($domain->key, $domain->title, $domain->periodFiltered), $this->queryBus->ask(new ListExportDomains()));
    }
}
