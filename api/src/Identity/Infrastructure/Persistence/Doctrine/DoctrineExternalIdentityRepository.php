<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\External\ExternalIdentity;
use App\Identity\Domain\External\ExternalIdentityRepository;
use App\Identity\Domain\External\IdentityProvider;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExternalIdentityRepository implements ExternalIdentityRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ExternalIdentity $identity): void
    {
        $this->entityManager->persist($identity);
    }

    public function find(IdentityProvider $provider, string $subject): ?ExternalIdentity
    {
        return $this->entityManager->getRepository(ExternalIdentity::class)->findOneBy(['provider' => $provider, 'subject' => $subject]);
    }
}
