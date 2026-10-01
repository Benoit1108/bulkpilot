<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence\Doctrine;

use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventId;
use App\Audit\Domain\AuditEventRepository;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\LatestFirst;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Events are inserted straight away (not at the unit of work's flush): an event recorded while
 * reading (a failed sign-in) is kept, and one recorded by a command commits or rolls back with it.
 */
final readonly class DoctrineAuditEventRepository implements AuditEventRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function append(AuditEvent $event): void
    {
        $this->entityManager->getConnection()->insert('audit_events', [
            'id' => $event->id()->value,
            'owner_id' => $event->ownerId()?->value,
            'pseudonym' => $event->pseudonym(),
            'action' => $event->action(),
            'details' => $event->details(),
            'source' => $event->source(),
            'created_at' => $event->createdAt(),
        ], [
            'details' => Types::JSON,
            'created_at' => Types::DATETIMETZ_IMMUTABLE,
        ]);
    }

    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?AuditEventId $beforeId = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('e')->from(AuditEvent::class, 'e')
            ->where('e.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id');

        /** @var list<AuditEvent> */
        return LatestFirst::page($query, 'e', $limit, $before, $beforeId, 'audit_event_id')->getQuery()->getResult();
    }

    public function allOf(UserId $userId): array
    {
        /** @var list<AuditEvent> */
        return $this->entityManager->createQueryBuilder()
            ->select('e')->from(AuditEvent::class, 'e')
            ->where('e.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id')
            ->orderBy('e.createdAt', 'ASC')->addOrderBy('e.id', 'ASC')
            ->getQuery()->getResult();
    }

    public function pseudonymise(UserId $userId, string $pseudonym): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE audit_events SET owner_id = NULL, pseudonym = :pseudonym, details = '{}' WHERE owner_id = :owner",
            ['pseudonym' => $pseudonym, 'owner' => $userId->value],
        );
    }

    public function purgeBefore(\DateTimeImmutable $time): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM audit_events WHERE created_at < :time',
            ['time' => $time],
            ['time' => Types::DATETIMETZ_IMMUTABLE],
        );
    }
}
