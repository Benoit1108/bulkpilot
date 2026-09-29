<?php

declare(strict_types=1);

namespace App\Progression\Domain\Photo;

/**
 * How the photo was taken, so that a comparison sets face against face.
 */
enum Pose: string
{
    case Front = 'front';
    case Side = 'side';
    case Back = 'back';
    case Other = 'other';
}
