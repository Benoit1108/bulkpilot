<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Shared\Application\Media\InvalidPhoto;

/**
 * Turns an uploaded image into what is kept: WebP, oriented, metadata dropped, and a thumbnail.
 */
interface ImageEncoder
{
    /**
     * @throws InvalidPhoto
     */
    public function encode(string $path): EncodedImage;
}
