<?php

declare(strict_types=1);

namespace App\System\Presentation\Api\Health;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\System\Application\Health\GetHealth;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Health>
 */
final readonly class HealthProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Health
    {
        $request = $context['request'] ?? null;
        $monitoring = $request instanceof Request && 'monitoring' === $request->query->getString('scope');

        return Health::fromReport($this->queryBus->ask(new GetHealth($monitoring)), $monitoring);
    }
}
