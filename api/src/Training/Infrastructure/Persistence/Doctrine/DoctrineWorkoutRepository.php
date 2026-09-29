<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineWorkoutRepository implements WorkoutRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Workout $workout): void
    {
        $this->entityManager->persist($workout);
    }

    public function find(WorkoutId $id): ?Workout
    {
        return $this->entityManager->find(Workout::class, $id);
    }

    public function getVisibleTo(WorkoutId $id, UserId $userId): Workout
    {
        $workout = $this->find($id);
        if (null === $workout || !$workout->isVisibleTo($userId)) {
            throw WorkoutNotFound::withId($id->value);
        }

        return $workout;
    }

    public function inProgressOf(UserId $userId): ?Workout
    {
        return $this->entityManager->getRepository(Workout::class)->findOneBy(['ownerId' => $userId, 'finishedAt' => null, 'deletedAt' => null]);
    }

    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $builder = $this->finished($userId)->orderBy('w.startedAt', 'DESC')->addOrderBy('w.id', 'DESC')->setMaxResults($limit);
        if (null !== $before) {
            $builder->andWhere('w.startedAt < :before')->setParameter('before', $before, 'datetimetz_immutable');
        }

        /** @var list<Workout> */
        return $builder->getQuery()->getResult();
    }

    /**
     * One query for every exercise of a workout: the owner index, then a LIKE per exercise
     * (a user has a few hundred workouts at most).
     */
    public function historyWith(UserId $userId, array $exerciseIds, ?\DateTimeImmutable $before = null): array
    {
        if ([] === $exerciseIds) {
            return [];
        }
        $builder = $this->finished($userId)->orderBy('w.startedAt', 'DESC')->addOrderBy('w.id', 'DESC')->setMaxResults(self::HISTORY_LIMIT);
        $any = $builder->expr()->orX();
        foreach ($exerciseIds as $i => $exerciseId) {
            $any->add(\sprintf('w.exerciseKey LIKE :exercise%d', $i));
            $builder->setParameter('exercise'.$i, '% '.$exerciseId->value.' %');
        }
        $builder->andWhere($any);
        if (null !== $before) {
            $builder->andWhere('w.startedAt < :before')->setParameter('before', $before, 'datetimetz_immutable');
        }

        /** @var list<Workout> $latest */
        $latest = $builder->getQuery()->getResult();

        return array_reverse($latest);
    }

    private function finished(UserId $userId): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(Workout::class, 'w')
            ->where('w.ownerId = :owner')
            ->andWhere('w.finishedAt IS NOT NULL')
            ->andWhere('w.deletedAt IS NULL')
            ->setParameter('owner', $userId, 'user_id');
    }
}
