<?php

declare(strict_types=1);

namespace App\Media\Application;

/**
 * Where files are kept: the local disk in development, a private S3-compatible bucket in production.
 */
interface MediaStorage
{
    public function write(string $key, string $contents, string $contentType): void;

    /** Writes a local file as it is, streamed (an export's archive may weigh hundreds of MB). */
    public function writeFile(string $key, string $path, string $contentType): void;

    /**
     * Copies the stored file to a local one, streamed.
     *
     * @throws \RuntimeException the file is missing or unreadable
     */
    public function readTo(string $key, string $path): void;

    /** Deleting a missing file changes nothing. */
    public function delete(string $key): void;

    /**
     * A link that works until `$expiresAt` only, never cached (`Cache-Control: private, no-store`),
     * offered as a download under `$downloadName` when given.
     */
    public function temporaryUrl(string $key, \DateTimeImmutable $expiresAt, ?string $downloadName = null): string;
}
