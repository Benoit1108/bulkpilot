<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Identifier\UserId;

/**
 * An important action, as it happened (spec §10, §105): who, what, what changed, by whom
 * (`user`, `coach`, `system`), when. Never edited; once the account is erased, only a pseudonym
 * and the action are left (ADR-0030).
 *
 * @phpstan-type Details array<string, string|int|float|bool|null>
 */
class AuditEvent
{
    public const int MAX_ACTION_LENGTH = 60;

    /**
     * @param Details $details
     */
    private function __construct(
        private readonly AuditEventId $id,
        private ?UserId $ownerId,
        private ?string $pseudonym,
        private readonly string $action,
        private array $details,
        private readonly string $source,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param Details $details
     */
    public static function record(AuditEventId $id, UserId $owner, string $action, array $details, string $source, \DateTimeImmutable $occurredOn): self
    {
        if (null === Action::tryFrom($action) || !Source::isKnown($source)) {
            throw InvalidAuditEvent::because(\sprintf('"%s" by "%s" is not an audit event.', $action, $source));
        }

        return new self($id, $owner, null, $action, $details, $source, $occurredOn);
    }

    public function id(): AuditEventId
    {
        return $this->id;
    }

    public function ownerId(): ?UserId
    {
        return $this->ownerId;
    }

    public function pseudonym(): ?string
    {
        return $this->pseudonym;
    }

    public function action(): string
    {
        return $this->action;
    }

    /**
     * @return Details
     */
    public function details(): array
    {
        return $this->details;
    }

    public function source(): string
    {
        return $this->source;
    }

    /** When it happened. */
    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
