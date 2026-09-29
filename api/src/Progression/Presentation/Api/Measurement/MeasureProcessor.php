<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Measurement;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Progression\Application\Measurement\AddMeasure;
use App\Progression\Application\Measurement\ArchiveMeasure;
use App\Progression\Application\Measurement\GetMeasure;
use App\Progression\Application\Measurement\RenameMeasure;
use App\Progression\Domain\Exception\MeasureNotFound;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Api\Ids;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<MeasureInput|null, Measure|null>
 */
final readonly class MeasureProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private QueryBus $queryBus,
        private RateLimiterFactoryInterface $progressionWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Measure
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->progressionWritesLimiter, 'progression_writes|'.$userId->value);

        if ($operation instanceof Post) {
            \assert($data instanceof MeasureInput);
            $id = new CustomMeasureId($this->uuids->generate());
            $this->commandBus->dispatch(new AddMeasure($id, $userId, $data->name));
        } else {
            $id = Ids::parse($uriVariables['key'] ?? null, CustomMeasureId::class, MeasureNotFound::withKey(...));
            if ($operation instanceof DeleteOperationInterface) {
                $this->commandBus->dispatch(new ArchiveMeasure($id, $userId));

                return null;
            }
            \assert($data instanceof MeasureInput);
            $this->commandBus->dispatch(new RenameMeasure($id, $userId, $data->name));
        }

        return Measure::of($this->queryBus->ask(new GetMeasure($userId, $id)));
    }
}
