<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Identifier\UserId;

/**
 * Append only: an event is written once and never changed, except that an erased account's
 * events keep only a pseudonym.
 */
interface AuditEventRepository
{
    /** Written at once, within the current transaction if any (a failed command leaves no trace of it). */
    public function append(AuditEvent $event): void;

    /**
     * @return list<AuditEvent> the user's events, the latest first
     */
    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?AuditEventId $beforeId = null): array;

    /**
     * @return list<AuditEvent> all of the user's events, the oldest first
     */
    public function allOf(UserId $userId): array;

    /** The user's events keep the action and the time only, under the pseudonym. */
    public function pseudonymise(UserId $userId, string $pseudonym): void;

    /** Removes the events older than that time; returns how many. */
    public function purgeBefore(\DateTimeImmutable $time): int;
}
