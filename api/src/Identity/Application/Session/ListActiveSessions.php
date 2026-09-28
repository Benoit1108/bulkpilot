<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSessionId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<SessionView>>
 */
final readonly class ListActiveSessions implements Query
{
    public function __construct(public UserId $userId, public ?UserSessionId $currentSessionId)
    {
    }
}
