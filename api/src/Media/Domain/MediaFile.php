<?php

declare(strict_types=1);

namespace App\Media\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * A file kept for its owner (ADR-0027): a photo (the re-encoded image and its thumbnail) or an
 * archive (an export's ZIP, ADR-0030), under opaque keys that say nothing about the user; the owner
 * is here, not in the key.
 */
class MediaFile extends AggregateRoot
{
    private function __construct(
        private readonly MediaId $id,
        private readonly UserId $ownerId,
        private readonly MediaKind $kind,
        private readonly string $key,
        private readonly ?string $thumbnailKey,
        private readonly string $contentType,
        private readonly ?int $width,
        private readonly ?int $height,
        private readonly int $bytes,
        private readonly \DateTimeImmutable $storedAt,
    ) {
    }

    public static function photo(MediaId $id, UserId $owner, int $width, int $height, int $bytes, \DateTimeImmutable $now): self
    {
        [$key, $thumbnailKey] = self::keysOf($id);

        return new self($id, $owner, MediaKind::Photo, $key, $thumbnailKey, 'image/webp', $width, $height, $bytes, $now);
    }

    public static function archive(MediaId $id, UserId $owner, string $contentType, int $bytes, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, MediaKind::Archive, self::archiveKeyOf($id), null, $contentType, null, null, $bytes, $now);
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

    /** The storage key of an archive, known before its record exists (to forget a write that never committed). */
    public static function archiveKeyOf(MediaId $id): string
    {
        return $id->value.'.zip';
    }

    /**
     * Everything written for it.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return null === $this->thumbnailKey ? [$this->key] : [$this->key, $this->thumbnailKey];
    }

    /** Erased with its owner's account: its files go once the erasure committed. */
    public function erase(\DateTimeImmutable $now): void
    {
        $this->recordThat(new MediaFileErased($this->id, $this->keys(), $now));
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    /** The user's own file of that kind. */
    public function isOwnedAs(UserId $userId, MediaKind $kind): bool
    {
        return $this->kind === $kind && $this->belongsTo($userId);
    }

    public function kind(): MediaKind
    {
        return $this->kind;
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

    public function thumbnailKey(): ?string
    {
        return $this->thumbnailKey;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
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
