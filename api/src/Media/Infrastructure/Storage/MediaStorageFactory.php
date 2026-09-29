<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use App\Media\Application\MediaStorage;
use AsyncAws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The storage chosen by MEDIA_STORAGE (services.yaml builds MediaStorage with it): "local"
 * (development, tests) or "s3" (production).
 */
final readonly class MediaStorageFactory
{
    public function __construct(
        private LocalMediaStorage $local,
        #[Autowire('%env(S3_ENDPOINT)%')]
        private string $endpoint,
        #[Autowire('%env(S3_PUBLIC_ENDPOINT)%')]
        private string $publicEndpoint,
        #[Autowire('%env(S3_REGION)%')]
        private string $region,
        #[Autowire('%env(S3_BUCKET)%')]
        private string $bucket,
        #[Autowire('%env(S3_ACCESS_KEY)%')]
        private string $accessKey,
        #[Autowire('%env(S3_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(bool:S3_PATH_STYLE)%')]
        private bool $pathStyle,
    ) {
    }

    public function create(string $storage): MediaStorage
    {
        return match ($storage) {
            'local' => $this->local,
            's3' => new S3MediaStorage($this->client($this->endpoint), $this->client('' === $this->publicEndpoint ? $this->endpoint : $this->publicEndpoint), $this->bucket),
            default => throw new \InvalidArgumentException(\sprintf('MEDIA_STORAGE must be "local" or "s3", "%s" given.', $storage)),
        };
    }

    /** An empty endpoint is Amazon's own; another provider gives its URL. */
    private function client(string $endpoint): S3Client
    {
        return new S3Client(array_filter([
            'endpoint' => '' === $endpoint ? null : $endpoint,
            'region' => $this->region,
            'accessKeyId' => $this->accessKey,
            'accessKeySecret' => $this->secretKey,
            'pathStyleEndpoint' => $this->pathStyle ? 'true' : 'false',
        ], static fn (?string $value): bool => null !== $value));
    }
}
