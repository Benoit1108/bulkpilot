<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\ClientConfig;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\System\Application\ClientConfig\GetClientConfig;

/**
 * @implements ProviderInterface<ClientConfig>
 */
final readonly class ClientConfigProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ClientConfig
    {
        return ClientConfig::of($this->queryBus->ask(new GetClientConfig()));
    }
}
