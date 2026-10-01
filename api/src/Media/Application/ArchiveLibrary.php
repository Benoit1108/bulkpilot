<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Domain\MediaFile;
use App\Media\Domain\MediaFileRepository;
use App\Media\Domain\MediaKind;
use App\Shared\Application\Media\Archives;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;

/**
 * Private archives (an export's ZIP, ADR-0030) on the storage configured, like photos: streamed
 * from the disk, never whole in memory.
 */
final readonly class ArchiveLibrary implements Archives
{
    public function __construct(private MediaFileRepository $files, private MediaStorage $storage, private ClockInterface $clock)
    {
    }

    public function store(UserId $owner, MediaId $id, string $path, string $contentType): void
    {
        $bytes = @filesize($path);
        if (false === $bytes) {
            throw new \RuntimeException(\sprintf('Cannot read "%s".', $path));
        }
        $file = MediaFile::archive($id, $owner, $contentType, $bytes, $this->clock->now());
        try {
            $this->storage->writeFile($file->key(), $path, $contentType);
        } catch (\Throwable $failure) {
            $this->storage->delete($file->key());

            throw $failure;
        }
        $this->files->save($file);
    }

    public function downloadLink(UserId $owner, MediaId $id, string $downloadName, int $ttlSeconds): string
    {
        $file = $this->files->find($id);
        if (null === $file || !$file->isOwnedAs($owner, MediaKind::Archive)) {
            throw MediaNotFound::withId($id->value);
        }

        return $this->storage->temporaryUrl($file->key(), $this->clock->now()->modify(\sprintf('+%d seconds', $ttlSeconds)), $downloadName);
    }

    public function delete(UserId $owner, MediaId $id): void
    {
        $file = $this->files->find($id);
        if (null === $file || !$file->isOwnedAs($owner, MediaKind::Archive)) {
            return;
        }
        foreach ($file->keys() as $key) {
            $this->storage->delete($key);
        }
        $this->files->remove($file);
    }

    public function forget(MediaId $id): void
    {
        $this->storage->delete(MediaFile::archiveKeyOf($id));
    }
}
