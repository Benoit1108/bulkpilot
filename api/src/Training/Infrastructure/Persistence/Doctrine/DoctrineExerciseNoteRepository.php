<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExerciseNoteRepository implements ExerciseNoteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ExerciseNote $note): void
    {
        $this->entityManager->persist($note);
    }

    public function remove(ExerciseNote $note): void
    {
        $this->entityManager->remove($note);
    }

    public function of(UserId $userId, ExerciseId $exerciseId): ?ExerciseNote
    {
        return $this->entityManager->getRepository(ExerciseNote::class)->findOneBy(['ownerId' => $userId, 'exerciseId' => $exerciseId]);
    }

    public function forExercises(UserId $userId, array $exerciseIds): array
    {
        if ([] === $exerciseIds) {
            return [];
        }
        /** @var list<ExerciseNote> $notes */
        $notes = $this->entityManager->createQueryBuilder()
            ->select('n')
            ->from(ExerciseNote::class, 'n')
            ->where('n.ownerId = :owner')
            ->andWhere('n.exerciseId IN (:ids)')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('ids', array_map(static fn (ExerciseId $id): string => $id->value, $exerciseIds))
            ->getQuery()
            ->getResult();

        $byExercise = [];
        foreach ($notes as $note) {
            $byExercise[$note->exerciseId()->value] = $note;
        }

        return $byExercise;
    }
}
