<?php

declare(strict_types=1);

namespace App\Media\Application\PersonalData;

use App\Media\Application\MediaStorage;
use App\Media\Domain\MediaFileErased;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * Deletes an erased file from the storage once its record's removal committed (ADR-0014): a
 * failed erasure keeps both, so that it can run again.
 */
final readonly class DeleteErasedFiles implements DomainEventSubscriber
{
    public function __construct(private MediaStorage $storage)
    {
    }

    public function __invoke(MediaFileErased $event): void
    {
        foreach ($event->keys as $key) {
            $this->storage->delete($key);
        }
    }
}
