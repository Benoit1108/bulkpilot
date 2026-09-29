<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProgramRepository implements ProgramRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Program $program): void
    {
        $this->entityManager->persist($program);
    }

    public function getVisibleTo(ProgramId $id, UserId $userId): Program
    {
        $program = $this->entityManager->find(Program::class, $id);
        if (null === $program || !$program->isVisibleTo($userId)) {
            throw ProgramNotFound::withId($id->value);
        }

        return $program;
    }

    public function activeOf(UserId $userId): ?Program
    {
        return $this->entityManager->getRepository(Program::class)->findOneBy(['ownerId' => $userId, 'active' => true, 'deletedAt' => null]);
    }

    public function countOf(UserId $userId): int
    {
        return $this->entityManager->getRepository(Program::class)->count(['ownerId' => $userId, 'deletedAt' => null]);
    }

    public function programsOf(UserId $userId): array
    {
        /** @var list<Program> */
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Program::class, 'p')
            ->where('p.ownerId = :owner')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.active', 'DESC')
            ->addOrderBy('LOWER(p.name)')
            ->addOrderBy('p.id')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }
}
