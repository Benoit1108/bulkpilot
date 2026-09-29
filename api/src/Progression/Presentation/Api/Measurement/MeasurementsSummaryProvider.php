<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Measurement\GetMeasurementsSummary;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\PeriodQuery;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<MeasurementsSummary>
 */
final readonly class MeasurementsSummaryProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser, private ClockInterface $clock)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MeasurementsSummary
    {
        $request = $context['request'] ?? null;
        [$period, $today] = PeriodQuery::read($request instanceof Request ? $request : null, $this->clock);
        $series = $this->queryBus->ask(new GetMeasurementsSummary($this->currentUser->id(), $period, $today));

        return new MeasurementsSummary($period->value, array_map(MeasureSeriesOutput::of(...), $series));
    }
}
