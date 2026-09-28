<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Journal\GetAverages;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<NutritionAverages>
 */
final readonly class NutritionAveragesProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NutritionAverages
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;

        return NutritionAverages::fromView($this->queryBus->ask(new GetAverages(
            $this->currentUser->id(),
            Days::parse($query?->get('today')),
            (int) $query?->getInt('days'),
        )));
    }
}
