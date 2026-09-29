<?php

declare(strict_types=1);

namespace App\Progression\Domain\Photo;

use App\Progression\Domain\DayWindow;
use App\Progression\Domain\Exception\InvalidProgressPhoto;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * A progress photo (spec §60): a day, a pose, and the file Media keeps. The photos of a day make
 * a session (§61), compared with another session with the weight and measurements of each day.
 */
class ProgressPhoto extends AggregateRoot
{
    /** Years of weekly sessions of front, side and back; bounds what one account stores. */
    public const int MAX_PER_USER = 500;

    private function __construct(
        private readonly ProgressPhotoId $id,
        private readonly UserId $ownerId,
        private readonly MediaId $mediaId,
        private readonly \DateTimeImmutable $day,
        private readonly Pose $pose,
        private readonly int $width,
        private readonly int $height,
        private readonly \DateTimeImmutable $addedAt,
    ) {
    }

    public static function add(ProgressPhotoId $id, UserId $owner, MediaId $mediaId, \DateTimeImmutable $day, Pose $pose, int $width, int $height, \DateTimeImmutable $now): self
    {
        if (!DayWindow::allows($day, $now)) {
            throw InvalidProgressPhoto::because('a photo is of a day that has come');
        }

        return new self($id, $owner, $mediaId, $day->setTime(0, 0), $pose, $width, $height, $now);
    }

    /** Deleted for good: its file goes after the deletion committed (a record never points at a missing file). */
    public function remove(\DateTimeImmutable $now): void
    {
        $this->recordThat(new ProgressPhotoRemoved($this->ownerId, $this->mediaId, $now));
    }

    public function id(): ProgressPhotoId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function mediaId(): MediaId
    {
        return $this->mediaId;
    }

    public function day(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function pose(): Pose
    {
        return $this->pose;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function addedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }
}
