<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;
use App\Training\Application\Exercise\GetExercise;
use App\Training\Application\Exercise\ListExercises;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Muscle;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Exercise>
 */
final readonly class ExerciseProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Exercise|list<Exercise>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Exercise|array
    {
        $userId = $this->currentUser->id();
        if (!$operation instanceof CollectionOperationInterface) {
            return Exercise::fromView($this->queryBus->ask(new GetExercise($userId, self::exerciseId($uriVariables['id'] ?? null))));
        }

        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $muscle = Muscle::tryFrom((string) $query?->getString('muscle'));

        return array_map(Exercise::fromView(...), $this->queryBus->ask(new ListExercises($userId, mb_substr((string) $query?->getString('q'), 0, 100), $muscle)));
    }

    public static function exerciseId(mixed $id): ExerciseId
    {
        return Ids::parse($id, ExerciseId::class, ExerciseNotFound::withId(...));
    }
}
