<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\Pose;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Adds a progress photo of a day: the uploaded file at `$path` is handed to Media, which keeps
 * only its re-encoded version.
 */
final readonly class AddProgressPhoto implements Command
{
    public function __construct(
        public ProgressPhotoId $photoId,
        public MediaId $mediaId,
        public UserId $userId,
        public \DateTimeImmutable $day,
        public Pose $pose,
        public string $path,
    ) {
    }
}
