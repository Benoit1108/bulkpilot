<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * The checks for the user now, as the app opens (synchronous: the list read next includes them).
 */
final readonly class CheckNotifications implements Command
{
    public function __construct(public UserId $userId)
    {
    }
}
