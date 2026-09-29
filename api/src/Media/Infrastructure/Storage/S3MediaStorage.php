<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use App\Media\Application\MediaStorage;
use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\S3Client;

/**
 * A private S3-compatible bucket (spec §90–91: production): files are written and deleted by the
 * server; the browser reads them through presigned links that expire and are never cached.
 * `$signer` signs for the endpoint the browser reaches, which may differ from the server's.
 */
final readonly class S3MediaStorage implements MediaStorage
{
    public function __construct(private S3Client $client, private S3Client $signer, private string $bucket)
    {
    }

    public function write(string $key, string $contents, string $contentType): void
    {
        $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $contents, 'ContentType' => $contentType])->resolve();
    }

    public function delete(string $key): void
    {
        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key])->resolve();
    }

    public function temporaryUrl(string $key, \DateTimeImmutable $expiresAt, ?string $downloadName = null): string
    {
        return $this->signer->presign(new GetObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ResponseCacheControl' => 'private, no-store',
            'ResponseContentDisposition' => null === $downloadName ? null : \sprintf('attachment; filename="%s"', $downloadName),
        ]), $expiresAt);
    }
}
