<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine;

use App\Progression\Domain\Exception\MeasureNotFound;
use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCustomMeasureRepository implements CustomMeasureRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(CustomMeasure $measure): void
    {
        $this->entityManager->persist($measure);
    }

    public function getOwnedBy(CustomMeasureId $id, UserId $userId): CustomMeasure
    {
        $measure = $this->entityManager->find(CustomMeasure::class, $id);
        if (null === $measure || !$measure->ownerId()->equals($userId)) {
            throw MeasureNotFound::withKey($id->value);
        }

        return $measure;
    }

    public function ofUser(UserId $userId, bool $withArchived = false): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(CustomMeasure::class, 'm')
            ->where('m.ownerId = :owner')
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setParameter('owner', $userId, 'user_id');
        if (!$withArchived) {
            $query->andWhere('m.archivedAt IS NULL');
        }

        /** @var list<CustomMeasure> */
        return $query->getQuery()->getResult();
    }
}
