<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\CalendarDays;
use App\Training\Application\Progress\GetTrainingWeek;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<TrainingWeek>
 */
final readonly class TrainingWeekProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TrainingWeek
    {
        $request = $context['request'] ?? null;
        $today = CalendarDays::today($request instanceof Request ? $request->query->getString('today') : null, $this->clock);

        return TrainingWeek::of($this->queryBus->ask(new GetTrainingWeek($this->currentUser->id(), $today)));
    }
}
