<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\Pose;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Shared\Application\Media\PhotoLinks;

final readonly class PhotoView
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $day,
        public Pose $pose,
        public int $width,
        public int $height,
        public PhotoLinks $links,
    ) {
    }

    public static function of(ProgressPhoto $photo, PhotoLinks $links): self
    {
        return new self($photo->id()->value, $photo->day(), $photo->pose(), $photo->width(), $photo->height(), $links);
    }

    /** « photo-2026-09-29-face.webp »: nothing personal in the name of a download. */
    public static function downloadName(ProgressPhoto $photo): string
    {
        return \sprintf('photo-%s-%s.webp', $photo->day()->format('Y-m-d'), $photo->pose()->value);
    }
}
