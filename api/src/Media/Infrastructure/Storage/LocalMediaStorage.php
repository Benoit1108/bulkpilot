<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Storage;

use App\Media\Application\MediaStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Files on the local disk (spec §91: development), served by LocalMediaController through signed links.
 */
final readonly class LocalMediaStorage implements MediaStorage
{
    /** The keys Media writes: a photo, its thumbnail, an archive (MediaFile). */
    public const string KEY = '[0-9a-f-]{36}(-thumbnail\.webp|\.webp|\.zip)';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/media/%kernel.environment%')]
        private string $directory,
        private SignedLocalUrls $urls,
    ) {
    }

    public function write(string $key, string $contents, string $contentType): void
    {
        $this->directory();
        if (false === file_put_contents($this->path($key), $contents, \LOCK_EX)) {
            throw new \RuntimeException(\sprintf('Cannot write "%s".', $key));
        }
    }

    public function writeFile(string $key, string $path, string $contentType): void
    {
        $this->directory();
        if (!@copy($path, $this->path($key))) {
            throw new \RuntimeException(\sprintf('Cannot write "%s".', $key));
        }
    }

    public function readTo(string $key, string $path): void
    {
        if (!@copy($this->path($key), $path)) {
            throw new \RuntimeException(\sprintf('Cannot read "%s".', $key));
        }
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    public function temporaryUrl(string $key, \DateTimeImmutable $expiresAt, ?string $downloadName = null): string
    {
        return $this->urls->url($key, $expiresAt, $downloadName);
    }

    public function path(string $key): string
    {
        if (1 !== preg_match('/^'.self::KEY.'$/', $key)) {
            throw new \InvalidArgumentException('Not a media key.');
        }

        return $this->directory.\DIRECTORY_SEPARATOR.$key;
    }

    private function directory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(\sprintf('Cannot create "%s".', $this->directory));
        }
    }
}
