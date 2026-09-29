<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Nutrition\Application\Journal\GetNutritionHistory;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\PeriodQuery;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<NutritionHistory>
 */
final readonly class NutritionHistoryProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NutritionHistory
    {
        $request = $context['request'] ?? null;
        [$period, $today] = PeriodQuery::read($request instanceof Request ? $request : null, $this->clock);

        return NutritionHistory::of($period, $this->queryBus->ask(new GetNutritionHistory($this->currentUser->id(), $period, $today)));
    }
}
