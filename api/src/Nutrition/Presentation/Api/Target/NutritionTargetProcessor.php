<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Target\GetTarget;
use App\Nutrition\Application\Target\SetTargets;
use App\Nutrition\Domain\Target\MacroTargets;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Presentation\Api\Shared\Days;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UuidGenerator;

/**
 * @implements ProcessorInterface<TargetInput, NutritionTarget>
 */
final readonly class NutritionTargetProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NutritionTarget
    {
        $userId = $this->currentUser->id();
        $targetId = new NutritionTargetId($this->uuids->generate());

        $this->commandBus->dispatch(new SetTargets(
            $targetId,
            $userId,
            Days::parse($data->effectiveFrom),
            new MacroTargets((int) $data->energyKcal, (int) $data->proteinGrams, (int) $data->carbohydrateGrams, (int) $data->fatGrams),
            $data->estimate?->request(),
        ));

        return NutritionTarget::fromView($this->queryBus->ask(new GetTarget($userId, $targetId)));
    }
}
