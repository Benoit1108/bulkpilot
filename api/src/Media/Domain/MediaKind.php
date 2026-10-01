<?php

declare(strict_types=1);

namespace App\Media\Domain;

/**
 * What a file is for: each library only ever hands out its own kind (a photo link never points at
 * an export's archive, nor the other way round).
 */
enum MediaKind: string
{
    case Photo = 'photo';
    case Archive = 'archive';
}
