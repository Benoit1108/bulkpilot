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
}
