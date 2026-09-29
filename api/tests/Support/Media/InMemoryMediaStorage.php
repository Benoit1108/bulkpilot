<?php

declare(strict_types=1);

namespace App\Tests\Support\Media;

use App\Media\Application\MediaStorage;

final class InMemoryMediaStorage implements MediaStorage
{
    /** @var array<string, string> */
    public array $files = [];

    /** A key containing this cannot be written (the bucket failing half-way). */
    public ?string $failingOn = null;

    public function write(string $key, string $contents, string $contentType): void
    {
        if (null !== $this->failingOn && str_contains($key, $this->failingOn)) {
            throw new \RuntimeException('bucket unreachable');
        }
        $this->files[$key] = $contents;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }

    public function temporaryUrl(string $key, \DateTimeImmutable $expiresAt, ?string $downloadName = null): string
    {
        return \sprintf('memory://%s?expires=%d%s', $key, $expiresAt->getTimestamp(), null === $downloadName ? '' : '&download='.$downloadName);
    }
}
