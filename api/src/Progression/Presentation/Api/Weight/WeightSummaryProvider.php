<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Weight\GetWeightSummary;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\PeriodQuery;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<WeightSummary>
 */
final readonly class WeightSummaryProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WeightSummary
    {
        $request = $context['request'] ?? null;
        [$period, $today] = PeriodQuery::read($request instanceof Request ? $request : null, $this->clock);

        return WeightSummary::of($period, $this->queryBus->ask(new GetWeightSummary($this->currentUser->id(), $period, $today)));
    }
}
