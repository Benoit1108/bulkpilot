<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportRequest;
use App\Export\Domain\ExportRequestRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class PurgeExpiredExportsHandler implements CommandHandler
{
    public function __construct(private ExportRequestRepository $exports, private EventBus $eventBus, private ClockInterface $clock)
    {
    }

    public function __invoke(PurgeExpiredExports $command): void
    {
        $now = $this->clock->now();
        foreach ($this->exports->expiredAt($now) as $export) {
            $export->expire($now);
            $this->exports->save($export);
            $this->eventBus->publish(...$export->pullDomainEvents());
        }
        // Still open long after it was asked for: its worker died (out of memory, restart). The
        // user may ask again.
        foreach ($this->exports->openSince($now->modify(\sprintf('-%d minutes', ExportRequest::STALE_MINUTES))) as $export) {
            $export->fail();
            $this->exports->save($export);
        }
    }
}
