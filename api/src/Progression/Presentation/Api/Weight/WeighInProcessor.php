<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Progression\Application\Weight\GetWeighIn;
use App\Progression\Application\Weight\RecordWeighIn;
use App\Progression\Application\Weight\RemoveWeighIn;
use App\Progression\Domain\Weight\WeighInId;
use App\Progression\Presentation\Api\ProgressionDays;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<WeighInInput|null, WeighIn|null>
 */
final readonly class WeighInProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private RateLimiterFactoryInterface $progressionWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?WeighIn
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->progressionWritesLimiter, 'progression_writes|'.$userId->value);
        $day = ProgressionDays::parse($uriVariables['day'] ?? null);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new RemoveWeighIn($userId, $day));

            return null;
        }
        \assert($data instanceof WeighInInput && null !== $data->weightKg);
        $this->commandBus->dispatch(new RecordWeighIn(new WeighInId($this->uuids->generate()), $userId, $day, $data->weightKg));

        return WeighIn::fromView($this->queryBus->ask(new GetWeighIn($userId, $day)));
    }
}
