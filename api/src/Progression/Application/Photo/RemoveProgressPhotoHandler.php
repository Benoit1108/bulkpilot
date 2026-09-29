<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class RemoveProgressPhotoHandler implements CommandHandler
{
    public function __construct(private ProgressPhotoRepository $photos, private EventBus $eventBus, private ClockInterface $clock)
    {
    }

    public function __invoke(RemoveProgressPhoto $command): void
    {
        $photo = $this->photos->getOwnedBy($command->photoId, $command->userId);
        $photo->remove($this->clock->now());
        $this->photos->remove($photo);
        $this->eventBus->publish(...$photo->pullDomainEvents());
    }
}
