<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Persistence\Doctrine;

use App\Export\Domain\Exception\ExportNotFound;
use App\Export\Domain\ExportId;
use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportRequestRepository;
use App\Export\Domain\ExportStatus;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExportRequestRepository implements ExportRequestRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ExportRequest $export): void
    {
        $this->entityManager->persist($export);
    }

    public function remove(ExportRequest $export): void
    {
        $this->entityManager->remove($export);
    }

    public function find(ExportId $id): ?ExportRequest
    {
        return $this->entityManager->find(ExportRequest::class, $id);
    }

    public function getOwnedBy(ExportId $id, UserId $userId): ExportRequest
    {
        $export = $this->find($id);
        if (null === $export || !$export->ownerId()->equals($userId)) {
            throw ExportNotFound::because(\sprintf('No export %s.', $id->value));
        }

        return $export;
    }

    public function hasOpen(UserId $userId): bool
    {
        return null !== $this->entityManager->getRepository(ExportRequest::class)->findOneBy(['ownerId' => $userId, 'status' => [ExportStatus::Queued, ExportStatus::Processing]]);
    }

    public function latestOf(UserId $userId, int $limit): array
    {
        /** @var list<ExportRequest> */
        return $this->entityManager->getRepository(ExportRequest::class)->findBy(['ownerId' => $userId], ['requestedAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function ofOwner(UserId $userId): array
    {
        /** @var list<ExportRequest> */
        return $this->entityManager->getRepository(ExportRequest::class)->findBy(['ownerId' => $userId]);
    }

    public function expiredAt(\DateTimeImmutable $now): array
    {
        /** @var list<ExportRequest> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')->from(ExportRequest::class, 'e')
            ->where('e.status = :ready')->andWhere('e.expiresAt <= :now')
            ->setParameter('ready', ExportStatus::Ready->value)
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->getQuery()->getResult();
    }

    public function openSince(\DateTimeImmutable $before): array
    {
        /** @var list<ExportRequest> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')->from(ExportRequest::class, 'e')
            ->where('e.status IN (:open)')->andWhere('e.requestedAt < :before')
            ->setParameter('open', [ExportStatus::Queued->value, ExportStatus::Processing->value])
            ->setParameter('before', $before, 'datetimetz_immutable')
            ->getQuery()->getResult();
    }
}
