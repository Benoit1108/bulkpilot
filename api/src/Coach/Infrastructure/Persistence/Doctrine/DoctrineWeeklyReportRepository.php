<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Persistence\Doctrine;

use App\Coach\Domain\Report\WeeklyReport;
use App\Coach\Domain\Report\WeeklyReportRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWeeklyReportRepository implements WeeklyReportRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(WeeklyReport $report): void
    {
        $this->entityManager->persist($report);
    }

    public function has(UserId $userId, \DateTimeImmutable $weekStart): bool
    {
        return null !== $this->find($userId, $weekStart);
    }

    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('r')->from(WeeklyReport::class, 'r')
            ->where('r.ownerId = :owner')->setParameter('owner', $userId, 'user_id')
            ->orderBy('r.weekStart', 'DESC')
            ->setMaxResults($limit);
        if (null !== $before) {
            $query->andWhere('r.weekStart < :before')->setParameter('before', $before->setTime(0, 0), 'date_immutable');
        }

        /** @var list<WeeklyReport> */
        return $query->getQuery()->getResult();
    }

    private function find(UserId $userId, \DateTimeImmutable $weekStart): ?WeeklyReport
    {
        return $this->entityManager->getRepository(WeeklyReport::class)->findOneBy(['ownerId' => $userId, 'weekStart' => $weekStart->setTime(0, 0)]);
    }
}
