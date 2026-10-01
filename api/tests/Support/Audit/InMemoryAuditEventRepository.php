<?php

declare(strict_types=1);

namespace App\Tests\Support\Audit;

use App\Audit\Domain\AuditEvent;
use App\Audit\Domain\AuditEventId;
use App\Audit\Domain\AuditEventRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryAuditEventRepository implements AuditEventRepository
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?AuditEventId $beforeId = null): array
    {
        $mine = array_values(array_filter($this->events, static function (AuditEvent $event) use ($userId, $before, $beforeId): bool {
            if (null === $event->ownerId() || !$event->ownerId()->equals($userId)) {
                return false;
            }
            if (null === $before) {
                return true;
            }

            return $event->createdAt() < $before || (null !== $beforeId && $event->createdAt()->format('U.u') === $before->format('U.u') && $event->id()->value < $beforeId->value);
        }));
        usort($mine, static fn (AuditEvent $a, AuditEvent $b): int => [$b->createdAt(), $b->id()->value] <=> [$a->createdAt(), $a->id()->value]);

        return \array_slice($mine, 0, $limit);
    }

    public function allOf(UserId $userId): array
    {
        return array_reverse($this->latestOf($userId, \PHP_INT_MAX));
    }

    public function pseudonymise(UserId $userId, string $pseudonym): void
    {
        $this->events = array_map(static function (AuditEvent $event) use ($userId, $pseudonym): AuditEvent {
            if (null === $event->ownerId() || !$event->ownerId()->equals($userId)) {
                return $event;
            }
            $erased = clone $event;
            (function () use ($pseudonym): void {
                $this->ownerId = null;
                $this->pseudonym = $pseudonym;
                $this->details = [];
            })->call($erased);

            return $erased;
        }, $this->events);
    }

    public function purgeBefore(\DateTimeImmutable $time): int
    {
        $before = \count($this->events);
        $this->events = array_values(array_filter($this->events, static fn (AuditEvent $event): bool => $event->createdAt() >= $time));

        return $before - \count($this->events);
    }
}
