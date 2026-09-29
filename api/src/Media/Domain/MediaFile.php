<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * A photo kept for its owner (ADR-0027): the re-encoded image and its thumbnail, under opaque
 * keys that say nothing about the user; the owner is here, not in the key.
 */
class MediaFile extends AggregateRoot
{
    private function __construct(
        private readonly MediaId $id,
        private readonly UserId $ownerId,
        private readonly string $key,
        private readonly string $thumbnailKey,
        private readonly string $contentType,
        private readonly int $width,
        private readonly int $height,
        private readonly int $bytes,
        private readonly \DateTimeImmutable $storedAt,
    ) {
    }

    public static function photo(MediaId $id, UserId $owner, int $width, int $height, int $bytes, \DateTimeImmutable $now): self
    {
        [$key, $thumbnailKey] = self::keysOf($id);

        return new self($id, $owner, $key, $thumbnailKey, 'image/webp', $width, $height, $bytes, $now);
    }

    /**
     * The storage keys of a photo: its id only, so that what was written can be found without a record.
     *
     * @return array{string, string} the photo's, then its thumbnail's
     */
    public static function keysOf(MediaId $id): array
    {
        return [$id->value.'.webp', $id->value.'-thumbnail.webp'];
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function id(): MediaId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function thumbnailKey(): string
    {
        return $this->thumbnailKey;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function storedAt(): \DateTimeImmutable
    {
        return $this->storedAt;
    }
}
