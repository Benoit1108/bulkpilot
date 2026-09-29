<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence\Doctrine;

use App\Media\Domain\MediaFile;
use App\Media\Domain\MediaFileRepository;
use App\Shared\Domain\Identifier\MediaId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMediaFileRepository implements MediaFileRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(MediaFile $file): void
    {
        $this->entityManager->persist($file);
    }

    public function remove(MediaFile $file): void
    {
        $this->entityManager->remove($file);
    }

    public function find(MediaId $id): ?MediaFile
    {
        return $this->entityManager->find(MediaFile::class, $id);
    }

    public function findMany(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<MediaFile> */
        return $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(MediaFile::class, 'f')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (MediaId $id): string => $id->value, $ids))
            ->getQuery()
            ->getResult();
    }
}
