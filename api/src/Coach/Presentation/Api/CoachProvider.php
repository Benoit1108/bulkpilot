<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Coach\Application\Analysis\GetCoach;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\CalendarDays;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Coach>
 */
final readonly class CoachProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Coach
    {
        $request = $context['request'] ?? null;
        $today = CalendarDays::today($request instanceof Request ? $request->query->getString('today') : null, $this->clock);

        return Coach::of($today, $this->queryBus->ask(new GetCoach($this->currentUser->id())));
    }
}
