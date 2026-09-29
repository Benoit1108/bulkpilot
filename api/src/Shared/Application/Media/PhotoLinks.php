<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

/**
 * Signed links valid until `$expiresAt` (a few minutes): shown to the user, never stored.
 */
final readonly class PhotoLinks
{
    public function __construct(
        public string $url,
        public string $thumbnailUrl,
        public string $downloadUrl,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
