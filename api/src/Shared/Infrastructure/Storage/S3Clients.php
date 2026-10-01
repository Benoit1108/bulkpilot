<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Storage;

use AsyncAws\S3\S3Client;

/**
 * An S3 client for a bucket of any S3-compatible provider (photos, backups: each with its own
 * credentials). An empty endpoint is Amazon's own.
 */
final class S3Clients
{
    public static function make(string $endpoint, string $region, string $accessKey, string $secretKey, bool $pathStyle): S3Client
    {
        return new S3Client(array_filter([
            'endpoint' => '' === $endpoint ? null : $endpoint,
            'region' => $region,
            'accessKeyId' => $accessKey,
            'accessKeySecret' => $secretKey,
            'pathStyleEndpoint' => $pathStyle ? 'true' : 'false',
        ], static fn (?string $value): bool => null !== $value));
    }
}
