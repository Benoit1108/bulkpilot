<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * A photo with fresh links (the ones shown expire after a few minutes).
 *
 * @implements Query<PhotoView>
 */
final readonly class GetProgressPhoto implements Query
{
    public function __construct(public UserId $userId, public ProgressPhotoId $photoId)
    {
    }
}
