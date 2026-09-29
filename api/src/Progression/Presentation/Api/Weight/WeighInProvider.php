<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Weight\GetWeighIn;
use App\Progression\Application\Weight\ListWeighIns;
use App\Progression\Presentation\Api\ProgressionDays;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Cursors;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<WeighIn>
 */
final readonly class WeighInProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return WeighIn|list<WeighIn>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WeighIn|array
    {
        $userId = $this->currentUser->id();
        if (!$operation instanceof CollectionOperationInterface) {
            return WeighIn::fromView($this->queryBus->ask(new GetWeighIn($userId, ProgressionDays::parse($uriVariables['day'] ?? null))));
        }

        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $before = Cursors::day($query?->getString('before'), 'before');

        return array_map(WeighIn::fromView(...), $this->queryBus->ask(new ListWeighIns($userId, $query?->getInt('limit', 30) ?? 30, $before)));
    }
}
