<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

use App\Shared\Domain\Exception\NotFound;

final class MediaNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Media "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'media.not_found';
    }
}
