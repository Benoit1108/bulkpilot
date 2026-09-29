<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Workout;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Ids;
use App\Training\Application\Workout\GetWorkout;
use App\Training\Application\Workout\GetWorkoutInProgress;
use App\Training\Application\Workout\ListWorkouts;
use App\Training\Application\Workout\PreviewWorkout;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Workout\WorkoutId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<Workout|WorkoutPlan|WorkoutSummary>
 */
final readonly class WorkoutProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Workout|WorkoutPlan|list<Workout>|list<WorkoutSummary>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Workout|WorkoutPlan|array
    {
        $userId = $this->currentUser->id();
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;

        if ('workouts_history' === $operation->getName()) {
            $workouts = $this->queryBus->ask(new ListWorkouts($userId, $query?->getInt('limit', 20) ?? 20, self::before($query?->getString('before'))));

            return array_map(WorkoutSummary::of(...), $workouts);
        }
        if ('workouts_in_progress' === $operation->getName()) {
            $current = $this->queryBus->ask(new GetWorkoutInProgress($userId));

            return null === $current ? [] : [Workout::fromView($current)];
        }
        if (WorkoutPlan::class === $operation->getClass()) {
            $programId = Ids::parse($query?->getString('programId'), ProgramId::class, ProgramNotFound::withId(...));

            return WorkoutPlan::fromView($this->queryBus->ask(new PreviewWorkout($userId, $programId, $query?->getInt('day') ?? 0)));
        }

        return Workout::fromView($this->queryBus->ask(new GetWorkout($userId, self::workoutId($uriVariables['id'] ?? null))));
    }

    public static function workoutId(mixed $id): WorkoutId
    {
        return Ids::parse($id, WorkoutId::class, WorkoutNotFound::withId(...));
    }

    /**
     * Any ISO 8601 time; an unreadable one is refused rather than ignored (a client paging on
     * it would get the first page again and again).
     */
    private static function before(?string $before): ?\DateTimeImmutable
    {
        if (null === $before || '' === $before) {
            return null;
        }
        try {
            return new \DateTimeImmutable($before);
        } catch (\Exception) {
            throw new BadRequestHttpException('"before" is an ISO 8601 date-time.');
        }
    }
}
