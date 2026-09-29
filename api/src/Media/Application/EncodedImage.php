<?php

declare(strict_types=1);

namespace App\Media\Application;

final readonly class EncodedImage
{
    public function __construct(public string $image, public string $thumbnail, public int $width, public int $height)
    {
    }
}
