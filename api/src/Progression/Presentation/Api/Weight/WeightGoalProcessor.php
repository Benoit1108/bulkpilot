<?php

declare(strict_types=1);

namespace App\Progression\Presentation\Api\Weight;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Progression\Application\Goal\EndWeightGoal;
use App\Progression\Application\Goal\GetWeightGoal;
use App\Progression\Application\Goal\SetWeightGoal;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<WeightGoalInput|null, WeightGoal|null>
 */
final readonly class WeightGoalProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private ClockInterface $clock,
        private RateLimiterFactoryInterface $progressionWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?WeightGoal
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->progressionWritesLimiter, 'progression_writes|'.$userId->value);

        if ($operation instanceof DeleteOperationInterface) {
            $this->commandBus->dispatch(new EndWeightGoal($userId));

            return null;
        }
        \assert($data instanceof WeightGoalInput && null !== $data->targetKg);
        $this->commandBus->dispatch(new SetWeightGoal(new WeightGoalId($this->uuids->generate()), $userId, $data->targetKg, $data->milestonesKg));

        return WeightGoal::of($this->queryBus->ask(new GetWeightGoal($userId, $this->clock->now())));
    }
}
