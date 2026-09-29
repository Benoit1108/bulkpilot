<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine;

use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMeasurementDayRepository implements MeasurementDayRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(MeasurementDay $day): void
    {
        $this->entityManager->persist($day);
    }

    public function remove(MeasurementDay $day): void
    {
        $this->entityManager->remove($day);
    }

    public function on(UserId $userId, \DateTimeImmutable $day): ?MeasurementDay
    {
        /** @var MeasurementDay|null */
        return $this->entityManager->getRepository(MeasurementDay::class)->findOneBy(['ownerId' => $userId, 'day' => $day->setTime(0, 0)]);
    }

    public function ofUser(UserId $userId): array
    {
        /** @var list<MeasurementDay> */
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MeasurementDay::class, 'm')
            ->where('m.ownerId = :owner')
            ->orderBy('m.day', 'ASC')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }
}
