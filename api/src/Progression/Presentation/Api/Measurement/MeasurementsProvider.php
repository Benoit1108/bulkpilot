<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Progression\Application\Measurement\GetMeasurements;
use App\Progression\Presentation\Api\ProgressionDays;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<Measurements>
 */
final readonly class MeasurementsProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Measurements
    {
        return Measurements::of($this->queryBus->ask(new GetMeasurements($this->currentUser->id(), ProgressionDays::parse($uriVariables['day'] ?? null))));
    }
}
