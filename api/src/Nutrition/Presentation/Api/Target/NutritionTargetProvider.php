<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Target\ListTargetHistory;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<NutritionTarget>
 */
final readonly class NutritionTargetProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return list<NutritionTarget>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(NutritionTarget::fromView(...), $this->queryBus->ask(new ListTargetHistory($this->currentUser->id())));
    }
}
