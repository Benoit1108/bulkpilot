<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Journal\GetDay;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<NutritionDay>
 */
final readonly class NutritionDayProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NutritionDay
    {
        return NutritionDay::fromView($this->queryBus->ask(new GetDay($this->currentUser->id(), Days::parse($uriVariables['day'] ?? null))));
    }
}
