<?php

declare(strict_types=1);

namespace App\Tests\Support\Media;

use App\Media\Domain\MediaFile;
use App\Media\Domain\MediaFileRepository;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryMediaFileRepository implements MediaFileRepository
{
    /** @var array<string, MediaFile> */
    public array $files = [];

    public function save(MediaFile $file): void
    {
        $this->files[$file->id()->value] = $file;
    }

    public function remove(MediaFile $file): void
    {
        unset($this->files[$file->id()->value]);
    }

    public function find(MediaId $id): ?MediaFile
    {
        return $this->files[$id->value] ?? null;
    }

    public function ofOwner(UserId $ownerId): array
    {
        return array_values(array_filter($this->files, static fn (MediaFile $file): bool => $file->belongsTo($ownerId)));
    }

    public function findMany(array $ids): array
    {
        return array_values(array_filter(array_map(fn (MediaId $id): ?MediaFile => $this->files[$id->value] ?? null, $ids)));
    }
}
