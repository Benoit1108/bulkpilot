<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Progression\Domain\Exception\ProgressPhotoNotFound;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Progression\Domain\Photo\ProgressPhotoRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryProgressPhotoRepository implements ProgressPhotoRepository
{
    /** @var array<string, ProgressPhoto> in the order they were added */
    public array $photos = [];

    public function save(ProgressPhoto $photo): void
    {
        $this->photos[$photo->id()->value] = $photo;
    }

    public function remove(ProgressPhoto $photo): void
    {
        unset($this->photos[$photo->id()->value]);
    }

    public function getOwnedBy(ProgressPhotoId $id, UserId $userId): ProgressPhoto
    {
        $photo = $this->photos[$id->value] ?? null;
        if (null === $photo || !$photo->ownerId()->equals($userId)) {
            throw ProgressPhotoNotFound::withId($id->value);
        }

        return $photo;
    }

    public function ofUser(UserId $userId): array
    {
        $photos = array_values(array_filter($this->photos, static fn (ProgressPhoto $photo): bool => $photo->ownerId()->equals($userId)));
        usort($photos, static fn (ProgressPhoto $a, ProgressPhoto $b): int => $b->day() <=> $a->day());

        return $photos;
    }

    public function countOf(UserId $userId): int
    {
        return \count($this->ofUser($userId));
    }
}
