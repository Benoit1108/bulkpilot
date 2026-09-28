<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Target\EstimateTargets;
use App\Shared\Application\Bus\Query\QueryBus;

/**
 * @implements ProcessorInterface<EstimateInput, EnergyEstimate>
 */
final readonly class EnergyEstimateProcessor implements ProcessorInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnergyEstimate
    {
        return EnergyEstimate::of($this->queryBus->ask(new EstimateTargets($data->request())));
    }
}
