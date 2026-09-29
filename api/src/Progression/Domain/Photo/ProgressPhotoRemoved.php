<?php

declare(strict_types=1);

namespace App\Progression\Domain\Photo;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * A progress photo was deleted: its file goes once the deletion committed.
 */
final readonly class ProgressPhotoRemoved implements DomainEvent
{
    public function __construct(public UserId $userId, public MediaId $mediaId, public \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
