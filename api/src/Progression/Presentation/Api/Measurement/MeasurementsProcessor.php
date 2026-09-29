<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Progression\Application\Measurement\GetMeasurements;
use App\Progression\Application\Measurement\RecordMeasurements;
use App\Progression\Application\Measurement\RemoveMeasurements;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Progression\Presentation\Api\ProgressionDays;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<MeasurementsInput|null, Measurements|null>
 */
final readonly class MeasurementsProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private RateLimiterFactoryInterface $progressionWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Measurements
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->progressionWritesLimiter, 'progression_writes|'.$userId->value);
        $day = ProgressionDays::parse($uriVariables['day'] ?? null);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new RemoveMeasurements($userId, $day));

            return null;
        }
        \assert($data instanceof MeasurementsInput);
        // JSON object keys that look like numbers come back as integers: measure keys are strings.
        $centimetres = [];
        foreach ($data->centimetres as $key => $cm) {
            $centimetres[(string) $key] = $cm;
        }
        $this->commandBus->dispatch(new RecordMeasurements(new MeasurementDayId($this->uuids->generate()), $userId, $day, $centimetres));

        return Measurements::of($this->queryBus->ask(new GetMeasurements($userId, $day)));
    }
}
