<?php

declare(strict_types=1);

namespace App\Shared\Application\Media;

final readonly class StoredPhoto
{
    public function __construct(public int $width, public int $height)
    {
    }
}
