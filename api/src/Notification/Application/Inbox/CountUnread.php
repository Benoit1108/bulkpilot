<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<int>
 */
final readonly class CountUnread implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
