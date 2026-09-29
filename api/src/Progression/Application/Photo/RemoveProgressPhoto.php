<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Deletes a progress photo for good, its file included (ADR-0027: a real deletion, at once).
 */
final readonly class RemoveProgressPhoto implements Command
{
    public function __construct(public UserId $userId, public ProgressPhotoId $photoId)
    {
    }
}
