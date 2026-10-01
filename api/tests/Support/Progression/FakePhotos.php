<?php

declare(strict_types=1);

namespace App\Tests\Support\Progression;

use App\Shared\Application\Media\InvalidPhoto;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Application\Media\PhotoLinks;
use App\Shared\Application\Media\Photos;
use App\Shared\Application\Media\StoredPhoto;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * The Photos port without files: a path of "bad" is refused, anything else is a 1200 × 1600 photo.
 */
final class FakePhotos implements Photos
{
    /** @var array<string, string> owner by media id */
    public array $stored = [];

    public function store(UserId $owner, MediaId $id, string $path): StoredPhoto
    {
        if ('bad' === $path) {
            throw InvalidPhoto::because('a photo is a JPEG, PNG or WebP image');
        }
        $this->stored[$id->value] = $owner->value;

        return new StoredPhoto(1200, 1600);
    }

    public function links(UserId $owner, MediaId $id, string $downloadName): PhotoLinks
    {
        if (($this->stored[$id->value] ?? null) !== $owner->value) {
            throw MediaNotFound::withId($id->value);
        }

        return new PhotoLinks('https://photos/'.$id->value, 'https://photos/'.$id->value.'/thumbnail', 'https://photos/'.$id->value.'?download='.$downloadName, new \DateTimeImmutable('2026-09-29 08:05:00'));
    }

    public function linksFor(UserId $owner, array $downloadNames): array
    {
        $links = [];
        foreach ($downloadNames as $id => $name) {
            if (($this->stored[$id] ?? null) === $owner->value) {
                $links[$id] = $this->links($owner, new MediaId($id), $name);
            }
        }

        return $links;
    }

    /** @var list<string> media ids forgotten */
    public array $forgotten = [];

    public function forget(MediaId $id): void
    {
        $this->forgotten[] = $id->value;
        unset($this->stored[$id->value]);
    }

    public function copyTo(UserId $owner, MediaId $id, string $path): void
    {
        if (($this->stored[$id->value] ?? null) !== $owner->value) {
            throw MediaNotFound::withId($id->value);
        }
        file_put_contents($path, 'photo '.$id->value);
    }

    public function delete(UserId $owner, MediaId $id): void
    {
        if (($this->stored[$id->value] ?? null) === $owner->value) {
            unset($this->stored[$id->value]);
        }
    }
}
