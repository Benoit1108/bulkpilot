<?php

declare(strict_types=1);

namespace App\Audit\Application\Activity;

use App\Audit\Domain\AuditEvent;

/**
 * @phpstan-import-type Details from AuditEvent
 */
final readonly class ActivityView
{
    /**
     * @param Details $details
     */
    public function __construct(public string $id, public string $action, public array $details, public string $source, public \DateTimeImmutable $occurredAt)
    {
    }

    public static function of(AuditEvent $event): self
    {
        return new self($event->id()->value, $event->action(), $event->details(), $event->source(), $event->createdAt());
    }
}
