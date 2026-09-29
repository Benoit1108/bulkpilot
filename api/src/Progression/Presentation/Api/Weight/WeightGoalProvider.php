<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Goal\GetWeightGoal;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\CalendarDays;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<WeightGoal>
 */
final readonly class WeightGoalProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WeightGoal
    {
        $request = $context['request'] ?? null;
        $today = CalendarDays::today($request instanceof Request ? $request->query->getString('today') : null, $this->clock);

        return WeightGoal::of($this->queryBus->ask(new GetWeightGoal($this->currentUser->id(), $today)));
    }
}
