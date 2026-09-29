<?php

declare(strict_types=1);

namespace App\Progression\Application\Photo;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The photo sessions, the most recent first, with links valid a few minutes.
 *
 * @implements Query<list<PhotoSessionView>>
 */
final readonly class ListPhotoSessions implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
