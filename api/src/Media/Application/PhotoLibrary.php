<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Domain\MediaFile;
use App\Media\Domain\MediaFileRepository;
use App\Media\Domain\MediaKind;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Application\Media\PhotoLinks;
use App\Shared\Application\Media\Photos;
use App\Shared\Application\Media\StoredPhoto;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;

/**
 * The photos a context keeps (ADR-0027), on the storage configured.
 */
final readonly class PhotoLibrary implements Photos
{
    /** Links are shown for a few minutes, and asked again after. */
    public const int LINK_SECONDS = 300;

    public function __construct(
        private MediaFileRepository $files,
        private MediaStorage $storage,
        private ImageEncoder $encoder,
        private ClockInterface $clock,
    ) {
    }

    public function store(UserId $owner, MediaId $id, string $path): StoredPhoto
    {
        $encoded = $this->encoder->encode($path);
        $file = MediaFile::photo($id, $owner, $encoded->width, $encoded->height, \strlen($encoded->image), $this->clock->now());
        [$key, $thumbnailKey] = MediaFile::keysOf($id);
        try {
            $this->storage->write($key, $encoded->image, $file->contentType());
            $this->storage->write($thumbnailKey, $encoded->thumbnail, $file->contentType());
        } catch (\Throwable $failure) {
            $this->forget($id);

            throw $failure;
        }
        $this->files->save($file);

        return new StoredPhoto($encoded->width, $encoded->height);
    }

    public function links(UserId $owner, MediaId $id, string $downloadName): PhotoLinks
    {
        return $this->signed($this->owned($owner, $id), $downloadName, $this->expiry());
    }

    public function linksFor(UserId $owner, array $downloadNames): array
    {
        $expiresAt = $this->expiry();
        $links = [];
        foreach ($this->files->findMany(array_map(static fn (string $id): MediaId => new MediaId($id), array_keys($downloadNames))) as $file) {
            if ($file->isOwnedAs($owner, MediaKind::Photo)) {
                $links[$file->id()->value] = $this->signed($file, $downloadNames[$file->id()->value] ?? 'photo.webp', $expiresAt);
            }
        }

        return $links;
    }

    public function delete(UserId $owner, MediaId $id): void
    {
        $file = $this->files->find($id);
        if (null === $file || !$file->isOwnedAs($owner, MediaKind::Photo)) {
            return;
        }
        foreach ($file->keys() as $key) {
            $this->storage->delete($key);
        }
        $this->files->remove($file);
    }

    public function copyTo(UserId $owner, MediaId $id, string $path): void
    {
        $this->storage->readTo($this->owned($owner, $id)->key(), $path);
    }

    public function forget(MediaId $id): void
    {
        foreach (MediaFile::keysOf($id) as $key) {
            $this->storage->delete($key);
        }
    }

    private function expiry(): \DateTimeImmutable
    {
        return $this->clock->now()->modify(\sprintf('+%d seconds', self::LINK_SECONDS));
    }

    private function signed(MediaFile $file, string $downloadName, \DateTimeImmutable $expiresAt): PhotoLinks
    {
        return new PhotoLinks(
            $this->storage->temporaryUrl($file->key(), $expiresAt),
            $this->storage->temporaryUrl((string) $file->thumbnailKey(), $expiresAt),
            $this->storage->temporaryUrl($file->key(), $expiresAt, $downloadName),
            $expiresAt,
        );
    }

    private function owned(UserId $owner, MediaId $id): MediaFile
    {
        $file = $this->files->find($id);
        if (null === $file || !$file->isOwnedAs($owner, MediaKind::Photo)) {
            throw MediaNotFound::withId($id->value);
        }

        return $file;
    }
}
