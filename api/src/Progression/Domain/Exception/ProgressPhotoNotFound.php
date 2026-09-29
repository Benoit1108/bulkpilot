<?php

declare(strict_types=1);

namespace App\Progression\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class ProgressPhotoNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Progress photo "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'progression.photo_not_found';
    }
}
