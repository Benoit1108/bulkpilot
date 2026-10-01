<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportExpired;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use App\Shared\Application\Media\Archives;

/**
 * Deletes an expired export's archive once the expiry committed (ADR-0014).
 */
final readonly class DeleteExpiredArchive implements DomainEventSubscriber
{
    public function __construct(private Archives $archives)
    {
    }

    public function __invoke(ExportExpired $event): void
    {
        $this->archives->delete($event->userId, $event->archiveId);
    }
}
