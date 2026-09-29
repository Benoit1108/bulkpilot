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

    public function latestOf(UserId $userId, int $limit): array
    {
        /** @var list<WeeklyReport> */
        return $this->entityManager->getRepository(WeeklyReport::class)->findBy(['ownerId' => $userId], ['weekStart' => 'DESC'], $limit);
    }

    private function find(UserId $userId, \DateTimeImmutable $weekStart): ?WeeklyReport
    {
        return $this->entityManager->getRepository(WeeklyReport::class)->findOneBy(['ownerId' => $userId, 'weekStart' => $weekStart->setTime(0, 0)]);
    }
}
