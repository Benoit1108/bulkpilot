<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine;

use App\Progression\Domain\Exception\ProgressPhotoNotFound;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProgressPhotoRepository implements ProgressPhotoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ProgressPhoto $photo): void
    {
        $this->entityManager->persist($photo);
    }

    public function remove(ProgressPhoto $photo): void
    {
        $this->entityManager->remove($photo);
    }

    public function getOwnedBy(ProgressPhotoId $id, UserId $userId): ProgressPhoto
    {
        $photo = $this->entityManager->find(ProgressPhoto::class, $id);
        if (null === $photo || !$photo->ownerId()->equals($userId)) {
            throw ProgressPhotoNotFound::withId($id->value);
        }

        return $photo;
    }

    public function ofUser(UserId $userId): array
    {
        /** @var list<ProgressPhoto> */
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(ProgressPhoto::class, 'p')
            ->where('p.ownerId = :owner')
            ->orderBy('p.day', 'DESC')
            ->addOrderBy('p.addedAt', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }

    public function countOf(UserId $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ProgressPhoto::class, 'p')
            ->where('p.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
