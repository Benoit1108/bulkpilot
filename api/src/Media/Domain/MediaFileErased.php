<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\MediaId;

/**
 * A file's record was erased with its owner's account: its stored files are deleted after the commit.
 */
final readonly class MediaFileErased implements DomainEvent
{
    /**
     * @param list<string> $keys
     */
    public function __construct(public MediaId $mediaId, public array $keys, public \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
