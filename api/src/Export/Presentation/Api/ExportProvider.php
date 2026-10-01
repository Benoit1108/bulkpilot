<?php

declare(strict_types=1);

namespace App\Export\Presentation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Export\Application\GetExport;
use App\Export\Application\ListExports;
use App\Export\Domain\Exception\ExportNotFound;
use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;

/**
 * @implements ProviderInterface<Export>
 */
final readonly class ExportProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Export|list<Export>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Export|array
    {
        $userId = $this->currentUser->id();
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(Export::of(...), $this->queryBus->ask(new ListExports($userId)));
        }

        return Export::of($this->queryBus->ask(new GetExport(self::id($uriVariables['id'] ?? null), $userId)));
    }

    public static function id(mixed $id): ExportId
    {
        return Ids::parse($id, ExportId::class, static fn (string $value): ExportNotFound => ExportNotFound::because(\sprintf('No export %s.', $value)));
    }
}
