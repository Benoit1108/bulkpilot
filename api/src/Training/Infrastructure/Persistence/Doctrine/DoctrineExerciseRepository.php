<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Exercise\ExerciseSource;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Exercise\SearchKey;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExerciseRepository implements ExerciseRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Exercise $exercise): void
    {
        $this->entityManager->persist($exercise);
    }

    public function getVisibleTo(ExerciseId $id, UserId $userId): Exercise
    {
        $exercise = $this->entityManager->find(Exercise::class, $id);
        if (null === $exercise || !$exercise->isVisibleTo($userId)) {
            throw ExerciseNotFound::withId($id->value);
        }

        return $exercise;
    }

    /**
     * A few hundred rows at most per user: a LIKE per word is enough, no index needed.
     */
    public function search(UserId $userId, string $query, ?Muscle $muscle, int $limit): array
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Exercise::class, 'e')
            ->where('e.deletedAt IS NULL')
            ->andWhere('e.ownerId IS NULL OR e.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id')
            ->orderBy('CASE WHEN e.ownerId IS NULL THEN 1 ELSE 0 END')
            ->addOrderBy('e.searchKey')
            ->addOrderBy('e.id')
            ->setMaxResults($limit);
        foreach (SearchKey::words($query) as $i => $word) {
            $builder->andWhere(\sprintf('e.searchKey LIKE :word%d', $i))->setParameter('word'.$i, '%'.addcslashes($word, '%_\\').'%');
        }
        if (null !== $muscle) {
            $builder->andWhere('e.muscleKey LIKE :muscle')->setParameter('muscle', '% '.$muscle->value.' %');
        }

        /** @var list<Exercise> */
        return $builder->getQuery()->getResult();
    }

    public function usableAmong(UserId $userId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var list<Exercise> $found */
        $found = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Exercise::class, 'e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (ExerciseId $id): string => $id->value, $ids))
            ->getQuery()
            ->getResult();

        $usable = [];
        foreach ($found as $exercise) {
            if ($exercise->isUsableBy($userId)) {
                $usable[$exercise->id()->value] = $exercise;
            }
        }

        return $usable;
    }

    public function countCustomOf(UserId $userId): int
    {
        return $this->entityManager->getRepository(Exercise::class)->count(['ownerId' => $userId, 'deletedAt' => null]);
    }

    public function library(): array
    {
        $library = [];
        foreach ($this->entityManager->getRepository(Exercise::class)->findBy(['source' => ExerciseSource::Library]) as $exercise) {
            $library[(string) $exercise->libraryKey()] = $exercise;
        }

        return $library;
    }
}
