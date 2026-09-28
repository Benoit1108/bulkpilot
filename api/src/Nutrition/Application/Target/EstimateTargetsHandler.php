<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\EnergyCalculator;
use App\Nutrition\Domain\Target\EnergyEstimate;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class EstimateTargetsHandler implements QueryHandler
{
    public function __construct(private EnergyCalculator $calculator, private ClockInterface $clock)
    {
    }

    public function __invoke(EstimateTargets $query): EnergyEstimate
    {
        return $this->calculator->estimate($query->request->inputs($this->clock->now()));
    }
}
