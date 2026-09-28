<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Token\OneTimeToken;
use App\Identity\Domain\Token\OneTimeTokenId;
use App\Identity\Domain\Token\OneTimeTokenRepository;
use App\Identity\Domain\Token\TokenPurpose;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOneTimeTokenRepository implements OneTimeTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(OneTimeToken $token): void
    {
        $this->entityManager->persist($token);
    }

    public function find(OneTimeTokenId $id): ?OneTimeToken
    {
        return $this->entityManager->find(OneTimeToken::class, $id);
    }

    public function removeAllOf(UserId $userId, TokenPurpose $purpose): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(OneTimeToken::class, 't')
            ->where('t.userId = :userId')
            ->andWhere('t.purpose = :purpose')
            ->setParameter('userId', $userId, 'user_id')
            ->setParameter('purpose', $purpose->value)
            ->getQuery()
            ->execute();
    }
}
