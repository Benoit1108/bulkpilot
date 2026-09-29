<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\DayWindow;
use App\Progression\Domain\Exception\InvalidProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Media\Photos;
use Psr\Clock\ClockInterface;

final readonly class AddProgressPhotoHandler implements CommandHandler
{
    public function __construct(private ProgressPhotoRepository $photos, private Photos $media, private ClockInterface $clock)
    {
    }

    public function __invoke(AddProgressPhoto $command): void
    {
        $now = $this->clock->now();
        // Refused before anything is stored.
        if (!DayWindow::allows($command->day, $now)) {
            throw InvalidProgressPhoto::because('a photo is of a day that has come');
        }
        if ($this->photos->countOf($command->userId) >= ProgressPhoto::MAX_PER_USER) {
            throw InvalidProgressPhoto::because(\sprintf('at most %d photos', ProgressPhoto::MAX_PER_USER));
        }

        $stored = $this->media->store($command->userId, $command->mediaId, $command->path);
        $this->photos->save(ProgressPhoto::add($command->photoId, $command->userId, $command->mediaId, $command->day, $command->pose, $stored->width, $stored->height, $now));
    }
}
