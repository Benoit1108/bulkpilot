<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\User;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePreferencesRepository implements PreferencesRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Preferences $preferences): void
    {
        $this->entityManager->persist($preferences);
    }

    public function of(UserId $userId): ?Preferences
    {
        return $this->entityManager->find(Preferences::class, $userId);
    }

    public function timeZones(?UserId $only = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('u.id, p.timeZone')->from(User::class, 'u')
            ->leftJoin(Preferences::class, 'p', 'WITH', 'p.ownerId = u.id')
            // An account being deleted is closed: no scheduled work runs for it.
            ->where('u.deletionDueAt IS NULL')
            ->orderBy('u.registeredAt');
        if (null !== $only) {
            $query->andWhere('u.id = :id')->setParameter('id', $only, 'user_id');
        }
        /** @var list<array{id: UserId, timeZone: string|null}> $rows */
        $rows = $query->getQuery()->getResult();
        $zones = [];
        foreach ($rows as $row) {
            $zones[$row['id']->value] = $row['timeZone'] ?? TimeZone::DEFAULT;
        }

        return $zones;
    }
}
