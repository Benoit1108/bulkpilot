<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Where a context keeps a photo (ADR-0027), implemented by Media. Media owns the file and its
 * owner: every link or deletion checks the photo belongs to the user asking.
 */
interface Photos
{
    /**
     * Checks the file, re-encodes it (no metadata kept, no original stored) and keeps it for its owner.
     *
     * @param string $path an uploaded file, read then left to the caller
     *
     * @throws InvalidPhoto not an image one can keep (type, size, dimensions, animation)
     */
    public function store(UserId $owner, MediaId $id, string $path): StoredPhoto;

    /**
     * Short-lived signed links to the photo, its thumbnail, and a download under a neutral name.
     *
     * @throws MediaNotFound also when it belongs to someone else
     */
    public function links(UserId $owner, MediaId $id, string $downloadName): PhotoLinks;

    /**
     * The links of several photos at once (a gallery): one read, the photos not the owner's left out.
     *
     * @param array<string, string> $downloadNames by media id
     *
     * @return array<string, PhotoLinks> by media id
     */
    public function linksFor(UserId $owner, array $downloadNames): array;

    /** Deletes the photo and its thumbnail for good; deleting it again changes nothing. */
    public function delete(UserId $owner, MediaId $id): void;

    /**
     * Removes the files `store` wrote for a photo whose record never committed (the command
     * failed after it): nothing of them is left behind. Forgetting it again changes nothing.
     */
    public function forget(MediaId $id): void;
}
