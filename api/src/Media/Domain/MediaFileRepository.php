<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

interface MediaFileRepository
{
    public function save(MediaFile $file): void;

    public function remove(MediaFile $file): void;

    public function find(MediaId $id): ?MediaFile;

    /**
     * @return list<MediaFile>
     */
    public function ofOwner(UserId $ownerId): array;

    /**
     * @param list<MediaId> $ids
     *
     * @return list<MediaFile>
     */
    public function findMany(array $ids): array;
}
