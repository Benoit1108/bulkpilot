<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\PeriodQuery;
use App\Training\Application\Progress\GetTrainingAnalytics;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<TrainingAnalytics>
 */
final readonly class TrainingAnalyticsProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TrainingAnalytics
    {
        $request = $context['request'] ?? null;
        [$period, $today] = PeriodQuery::read($request instanceof Request ? $request : null, $this->clock);

        return TrainingAnalytics::of($period, $this->queryBus->ask(new GetTrainingAnalytics($this->currentUser->id(), $period, $today)));
    }
}
