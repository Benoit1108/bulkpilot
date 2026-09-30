<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class MarkAllRead implements Command
{
    public function __construct(public UserId $userId)
    {
    }
}
