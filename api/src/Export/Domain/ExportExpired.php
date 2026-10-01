<?php

declare(strict_types=1);

namespace App\Export\Domain;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * An export's archive is no longer offered: its file is deleted once that committed.
 */
final readonly class ExportExpired implements DomainEvent
{
    public function __construct(public ExportId $exportId, public UserId $userId, public MediaId $archiveId, public \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
