<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;

/**
 * Private files other than photos (an export's ZIP), kept by Media like photos (ADR-0027):
 * owner checked on every link and deletion.
 */
interface Archives
{
    /**
     * @param string $path a local file, read then left to the caller
     */
    public function store(UserId $owner, MediaId $id, string $path, string $contentType): void;

    /**
     * A signed download link under a neutral name, valid for `$ttlSeconds`.
     *
     * @throws MediaNotFound also when it belongs to someone else
     */
    public function downloadLink(UserId $owner, MediaId $id, string $downloadName, int $ttlSeconds): string;

    /** Deletes it for good; deleting it again changes nothing. */
    public function delete(UserId $owner, MediaId $id): void;

    /**
     * Removes the file `store` wrote for an archive whose record never committed; forgetting it
     * again changes nothing.
     */
    public function forget(MediaId $id): void;
}
