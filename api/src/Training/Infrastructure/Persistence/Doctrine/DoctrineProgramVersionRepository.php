<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine;

use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProgramVersionRepository implements ProgramVersionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ProgramVersion $version): void
    {
        $this->entityManager->persist($version);
    }

    public function currentOf(Program $program): ProgramVersion
    {
        return $this->entityManager->find(ProgramVersion::class, $program->currentVersionId()) ?? throw ProgramNotFound::withId($program->id()->value);
    }

    public function numbered(Program $program, int $number): ProgramVersion
    {
        return $this->entityManager->getRepository(ProgramVersion::class)->findOneBy(['programId' => $program->id(), 'number' => $number])
            ?? throw ProgramNotFound::withId($program->id()->value);
    }

    public function currentOfEach(array $programs): array
    {
        if ([] === $programs) {
            return [];
        }
        /** @var list<ProgramVersion> $found */
        $found = $this->entityManager->createQueryBuilder()
            ->select('v')
            ->from(ProgramVersion::class, 'v')
            ->where('v.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (Program $program): string => $program->currentVersionId()->value, $programs))
            ->getQuery()
            ->getResult();

        $byProgram = [];
        foreach ($found as $version) {
            $byProgram[$version->programId()->value] = $version;
        }

        return $byProgram;
    }

    /**
     * Numbers and dates only: the days of every version are not needed to list them.
     */
    public function historyOf(Program $program): array
    {
        /** @var list<array{number: int, createdAt: \DateTimeImmutable}> */
        return $this->entityManager->createQueryBuilder()
            ->select('v.number', 'v.createdAt')
            ->from(ProgramVersion::class, 'v')
            ->where('v.programId = :program')
            ->orderBy('v.number', 'DESC')
            ->setParameter('program', $program->id(), 'program_id')
            ->getQuery()
            ->getResult();
    }
}
