<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\ProgressPhotoRemoved;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use App\Shared\Application\Media\Photos;

/**
 * Deletes a removed photo's file, after the removal committed (ADR-0014): a failed commit keeps
 * both; a failed file deletion leaves a file Media still lists, erased with the account.
 */
final readonly class DeleteRemovedPhotoFile implements DomainEventSubscriber
{
    public function __construct(private Photos $photos)
    {
    }

    public function __invoke(ProgressPhotoRemoved $event): void
    {
        $this->photos->delete($event->userId, $event->mediaId);
    }
}
