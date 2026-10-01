<?php

declare(strict_types=1);

namespace App\Shared\Application\PersonalData;

use App\Shared\Domain\Identifier\MediaId;

/**
 * A stored file to put in the export under a readable name (`photos/2026-09-28-face.webp`).
 */
final readonly class ExportFile
{
    public function __construct(public MediaId $mediaId, public string $path)
    {
    }
}
