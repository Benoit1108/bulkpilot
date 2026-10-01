<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes are flushed by the command bus transaction.
 */
final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
    }

    public function get(UserId $id): User
    {
        return $this->entityManager->find(User::class, $id) ?? throw UserNotFound::withId($id);
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function find(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function lockForErasure(UserId $id): ?User
    {
        // The row lock first (a sign-in cancelling the deletion waits for the erasure's commit); the
        // worker starts every message with an empty unit of work, so the account is read after it.
        $this->entityManager->getConnection()->executeQuery('SELECT 1 FROM users WHERE id = :id FOR UPDATE', ['id' => $id->value]);

        return $this->find($id);
    }

    public function dueForErasure(\DateTimeImmutable $now): array
    {
        /** @var list<array{id: UserId}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.id')->from(User::class, 'u')
            ->where('u.deletionDueAt <= :now')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('u.deletionDueAt')
            ->getQuery()->getResult();

        return array_map(static fn (array $row): UserId => $row['id'], $rows);
    }
}
